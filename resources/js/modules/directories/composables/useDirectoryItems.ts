import {computed, reactive, ref, watch} from 'vue'
import {directoryRepository} from '@/modules/directories/repositories/directoryRepository'
import type {DirectoryItem, DirectorySchemaField} from '@/modules/directories/types/directory'

export interface FlatTreeItem extends DirectoryItem {
    depth: number
    hasChildren: boolean
    isExpanded: boolean
}

const OTHER_ITEM_ID = -1

export function useDirectoryItems(directoryId: string, defaultSort?: string | null, withOther = false) {
    const items = ref<DirectoryItem[]>([])
    const baseItems = ref<DirectoryItem[]>([])
    const loading = ref(false)
    const treeExpanded = reactive<Record<number, boolean>>({})
    const selectedIds = ref<Set<number>>(new Set())

    const searchQuery = ref('')
    const activeFilters = reactive<Record<string, string>>({})
    const activeFiltersTo = reactive<Record<string, string>>({})
    const activeFiltersMulti = reactive<Record<string, string[]>>({})
    const hasActiveFilters = computed(() =>
        searchQuery.value.trim() !== '' ||
        Object.values(activeFilters).some(v => String(v ?? '').trim() !== '') ||
        Object.values(activeFiltersTo).some(v => String(v ?? '').trim() !== '') ||
        Object.values(activeFiltersMulti).some(v => v.length > 0),
    )

    function parseDefaultSort(raw?: string | null): { key: string | null; dir: 'asc' | 'desc' } {
        if (!raw) return {key: null, dir: 'asc'}
        if (raw.startsWith('-')) return {key: raw.slice(1), dir: 'desc'}
        return {key: raw, dir: 'asc'}
    }

    const _default = parseDefaultSort(defaultSort)
    const sortKey = ref<string | null>(_default.key)
    const sortDir = ref<'asc' | 'desc'>(_default.dir)

    function toggleSort(key: string): void {
        if (sortKey.value !== key) {
            sortKey.value = key
            sortDir.value = 'desc'
        } else if (sortDir.value === 'desc') {
            sortDir.value = 'asc'
        } else if (key === _default.key) {
            sortDir.value = 'desc'
        } else {
            sortKey.value = _default.key
            sortDir.value = _default.dir
        }
    }

    function clearFilters(): void {
        searchQuery.value = ''
        Object.keys(activeFilters).forEach(k => {
            delete activeFilters[k]
        })
        Object.keys(activeFiltersTo).forEach(k => {
            delete activeFiltersTo[k]
        })
        Object.keys(activeFiltersMulti).forEach(k => {
            delete activeFiltersMulti[k]
        })
    }

    function toggleMultiFilter(fieldKey: string, value: string): void {
        const cur = activeFiltersMulti[fieldKey] ?? []
        const next = cur.includes(value) ? cur.filter(v => v !== value) : [...cur, value]
        if (next.length === 0) {
            delete activeFiltersMulti[fieldKey]
        } else {
            activeFiltersMulti[fieldKey] = next
        }
    }

    let currentVersionId: number | undefined
    let debounceTimer: ReturnType<typeof setTimeout> | null = null

    async function loadItems(versionId?: number): Promise<void> {
        currentVersionId = versionId
        if (!directoryId) {
            items.value = []
            baseItems.value = []
            return
        }
        loading.value = true
        try {
            const qs = new URLSearchParams()
            if (versionId) qs.set('filter[version_id]', String(versionId))
            if (withOther) qs.set('filter[with_other]', '1')
            if (searchQuery.value.trim()) qs.set('filter[q]', searchQuery.value.trim())
            if (sortKey.value) qs.set('sort', sortDir.value === 'desc' ? `-${sortKey.value}` : sortKey.value)
            Object.entries(activeFilters).forEach(([key, val]) => {
                const s = String(val ?? '').trim()
                if (s) qs.set(`filter[${key}]`, s)
            })
            Object.entries(activeFiltersTo).forEach(([key, val]) => {
                const s = String(val ?? '').trim()
                if (s) qs.set(`filter_to[${key}]`, s)
            })
            Object.entries(activeFiltersMulti).forEach(([key, vals]) => {
                vals.forEach(v => qs.append(`filter[${key}][]`, v))
            })
            const result = await directoryRepository.items(directoryId, qs)
            items.value = result.items
            if (!hasActiveFilters.value) baseItems.value = result.items
        } finally {
            loading.value = false
        }
    }

    watch([activeFilters, activeFiltersTo, activeFiltersMulti, searchQuery, sortKey, sortDir], () => {
        if (debounceTimer) clearTimeout(debounceTimer)
        debounceTimer = setTimeout(() => {
            void loadItems(currentVersionId)
        }, 300)
    }, {deep: true})

    const flatTree = computed((): FlatTreeItem[] => {
        const childrenOf = (parentId: number | null) =>
            items.value.filter(i => i.parent_id === parentId)

        const result: FlatTreeItem[] = []
        const walk = (nodes: DirectoryItem[], depth: number) => {
            for (const node of nodes) {
                const children = childrenOf(node.id)
                result.push({...node, depth, hasChildren: children.length > 0, isExpanded: !!treeExpanded[node.id]})
                if (treeExpanded[node.id]) walk(children, depth + 1)
            }
        }
        walk(childrenOf(null), 0)
        return result
    })

    function expandAll(): void {
        items.value.forEach(item => {
            if (items.value.some(i => i.parent_id === item.id)) treeExpanded[item.id] = true
        })
    }

    function collapseAll(): void {
        Object.keys(treeExpanded).forEach(k => {
            delete treeExpanded[Number(k)]
        })
    }

    const visibleIds = computed(() => flatTree.value.map(n => n.id).filter(id => id !== OTHER_ITEM_ID))
    const allSelected = computed(() => visibleIds.value.length > 0 && visibleIds.value.every(id => selectedIds.value.has(id)))
    const someSelected = computed(() => selectedIds.value.size > 0)

    function toggleSelect(id: number): void {
        const s = new Set(selectedIds.value)
        if (s.has(id)) s.delete(id)
        else s.add(id)
        selectedIds.value = s
    }

    function toggleSelectAll(): void {
        if (allSelected.value) selectedIds.value = new Set()
        else selectedIds.value = new Set(visibleIds.value)
    }

    const editDialogOpen = ref(false)
    const editingItemId = ref<number | null>(null)
    const editingParentId = ref<number | null>(null)
    const editingExternalKey = ref<string>('')
    const editFields = reactive<Record<string, string>>({})
    const editError = ref<string | null>(null)
    const editSaving = ref(false)

    function listOptions(field: DirectorySchemaField): string[] {
        if (field.options && field.options.length > 0) return field.options
        const all = new Set<string>()
        for (const item of baseItems.value) {
            const v = item.data[field.key]
            if (v != null) {
                const s = String(v)
                if (s !== '') all.add(s)
            }
        }
        return Array.from(all).sort()
    }

    function openAddItem(schemaFields: DirectorySchemaField[]): void {
        editingItemId.value = null
        editingParentId.value = null
        editingExternalKey.value = ''
        schemaFields.forEach(f => {
            editFields[f.key] = ''
        })
        editError.value = null
        editDialogOpen.value = true
    }

    function openEditItem(item: DirectoryItem, schemaFields: DirectorySchemaField[]): void {
        editingItemId.value = item.id
        editingParentId.value = item.parent_id
        editingExternalKey.value = item.external_key ?? ''
        schemaFields.forEach(f => {
            editFields[f.key] = item.data[f.key] ?? ''
        })
        editError.value = null
        editDialogOpen.value = true
    }

    async function saveItem(schemaFields: DirectorySchemaField[]): Promise<void> {
        editSaving.value = true
        editError.value = null
        try {
            const data = Object.fromEntries(schemaFields.map(f => [f.key, editFields[f.key] ?? '']))
            const payload = {
                parent_id: editingParentId.value,
                external_key: editingExternalKey.value.trim() || null,
                data,
            }
            if (editingItemId.value !== null) {
                const result = await directoryRepository.updateItem(directoryId, editingItemId.value, payload)
                const idx = items.value.findIndex(i => i.id === editingItemId.value)
                if (idx !== -1) items.value[idx] = result.item
            } else {
                const result = await directoryRepository.createItem(directoryId, payload)
                items.value = [...items.value, result.item]
            }
            editDialogOpen.value = false
        } catch (e: unknown) {
            editError.value = e instanceof Error ? e.message : 'Ошибка сохранения.'
        } finally {
            editSaving.value = false
        }
    }

    async function removeItem(item: DirectoryItem): Promise<void> {
        await directoryRepository.removeItem(directoryId, item.id)
        items.value = items.value.filter(i => i.id !== item.id && i.parent_id !== item.id)
    }

    async function deleteSelected(): Promise<void> {
        const ids = Array.from(selectedIds.value)
        await directoryRepository.bulkRemoveItems(directoryId, ids)
        items.value = items.value.filter(i => !selectedIds.value.has(i.id))
        selectedIds.value = new Set()
    }

    function fieldValue(item: DirectoryItem, key: string): string {
        return item.data[key] ?? '—'
    }

    function formatFilterDate(iso: string, withTime: boolean): string {
        if (!iso) return ''
        const d = new Date(iso)
        if (isNaN(d.getTime())) return iso
        const pad = (n: number) => String(n).padStart(2, '0')
        const datePart = `${pad(d.getDate())}.${pad(d.getMonth() + 1)}.${d.getFullYear()}`
        return withTime ? `${datePart} ${pad(d.getHours())}:${pad(d.getMinutes())}` : datePart
    }

    function isFilterActive(field: DirectorySchemaField): boolean {
        if (field.filter_type === 'list' && field.filter_multiple)
            return (activeFiltersMulti[field.key] ?? []).length > 0
        if (field.filter_operator === 'between')
            return !!(String(activeFilters[field.key] ?? '').trim() || String(activeFiltersTo[field.key] ?? '').trim())
        return !!String(activeFilters[field.key] ?? '').trim()
    }

    function clearFilter(field: DirectorySchemaField): void {
        delete activeFilters[field.key]
        delete activeFiltersTo[field.key]
        delete activeFiltersMulti[field.key]
    }

    function getFilterDisplayValue(field: DirectorySchemaField): string {
        if (field.filter_type === 'list' && field.filter_multiple) {
            const vals = activeFiltersMulti[field.key] ?? []
            return vals.length === 1 ? vals[0] : `${vals.length} выбрано`
        }
        if (field.filter_operator === 'between') {
            const isDatetime = field.filter_type === 'datetime'
            const from = formatFilterDate(activeFilters[field.key] ?? '', isDatetime)
            const to = formatFilterDate(activeFiltersTo[field.key] ?? '', isDatetime)
            if (from && to) return `${from} — ${to}`
            if (from) return `от ${from}`
            if (to) return `до ${to}`
            return ''
        }
        const val = activeFilters[field.key] ?? ''
        if (field.filter_type === 'boolean') return val === 'true' ? 'Да' : 'Нет'
        if (field.filter_type === 'date') return formatFilterDate(val, false)
        if (field.filter_type === 'datetime') return formatFilterDate(val, true)
        return val
    }

    function formatFieldValue(item: DirectoryItem, field: DirectorySchemaField): string {
        const raw = item.data[field.key] ?? null
        if (field.type === 'related_directory') return item.related?.[field.key]?.label ?? raw ?? '—'
        if (raw === null || raw === '') return '—'
        switch (field.type) {
            case 'boolean':
                return raw === 'true' || raw === '1' ? 'Да' : 'Нет'
            case 'date':
                try {
                    return new Date(raw).toLocaleDateString('ru-RU', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric'
                    })
                } catch {
                    return raw
                }
            case 'datetime':
                try {
                    return new Date(raw).toLocaleString('ru-RU', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    })
                } catch {
                    return raw
                }
            default:
                return raw
        }
    }

    return {
        items, loading, treeExpanded, selectedIds,
        searchQuery,
        activeFilters, activeFiltersTo, activeFiltersMulti, hasActiveFilters, clearFilters, toggleMultiFilter,
        sortKey, sortDir, toggleSort,
        flatTree, visibleIds, allSelected, someSelected,
        loadItems, expandAll, collapseAll,
        toggleSelect, toggleSelectAll,
        editDialogOpen, editingItemId, editingParentId, editingExternalKey, editFields, editError, editSaving,
        openAddItem, openEditItem, saveItem, removeItem, deleteSelected,
        listOptions, fieldValue, formatFieldValue,
        isFilterActive, clearFilter, getFilterDisplayValue,
    }
}
