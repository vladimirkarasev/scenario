import {computed, onMounted, ref} from 'vue'
import {actionCategoryRepository} from '@/modules/actions/repositories/actionCategoryRepository'
import type {ActionCategory} from '@/modules/actions/types/action'

export interface ActionSectionNode extends ActionCategory {
    children: ActionSectionNode[]
}

export interface FlatActionSectionItem {
    section: ActionSectionNode
    depth: number
    hasChildren: boolean
}

export function useActionSectionTree() {
    const sections = ref<ActionCategory[]>([])
    const loading = ref(false)
    const loadedParents = ref(new Set<string | null>())
    const activeSection = ref<string | 'all'>('all')
    const expandedIds = ref(new Set<string>())
    // Стабильный ref для фильтрации списка экшенов — обновляется один раз после прогрузки детей.
    const categoryFilterIds = ref<string[]>([])

    const sectionTree = computed<ActionSectionNode[]>(() => {
        const map = new Map<string, ActionSectionNode>()
        const roots: ActionSectionNode[] = []
        for (const c of sections.value) map.set(c.id, {...c, children: []})
        for (const c of sections.value) {
            const node = map.get(c.id)!
            if (c.parent_id !== null && map.has(c.parent_id)) {
                map.get(c.parent_id)!.children.push(node)
            } else if (c.parent_id === null || !map.has(c.parent_id)) {
                roots.push(node)
            }
        }
        return roots
    })

    const sidebarItems = computed<FlatActionSectionItem[]>(() => flatten(sectionTree.value, 0))

    const allSectionsFlat = computed<{ id: string; name: string; depth: number }[]>(() => {
        const result: { id: string; name: string; depth: number }[] = []

        function walk(nodes: ActionSectionNode[], depth: number): void {
            for (const n of nodes) {
                result.push({id: n.id, name: n.name, depth})
                if (n.children.length) walk(n.children, depth + 1)
            }
        }

        walk(sectionTree.value, 0)
        return result
    })

    const currentSectionName = computed(() =>
        activeSection.value === 'all'
            ? 'Все действия'
            : sections.value.find(c => c.id === activeSection.value)?.name ?? '—',
    )

    async function loadByParent(parentId: string | null): Promise<void> {
        if (loadedParents.value.has(parentId)) return
        loading.value = true
        try {
            const items = await actionCategoryRepository.byParent(parentId)
            const incoming = items.filter(c => !sections.value.some(s => s.id === c.id))
            sections.value = [...sections.value, ...incoming]
            loadedParents.value = new Set([...loadedParents.value, parentId])
        } catch { /* silent */
        } finally {
            loading.value = false
        }
    }

    onMounted(() => loadByParent(null))

    async function toggleExpand(id: string): Promise<void> {
        if (expandedIds.value.has(id)) {
            const next = new Set(expandedIds.value)
            next.delete(id)
            expandedIds.value = next
            return
        }
        const node = sections.value.find(s => s.id === id)
        if (node && node.children_count > 0) await loadByParent(id)
        const next = new Set(expandedIds.value)
        next.add(id)
        expandedIds.value = next
    }

    function expandParents(nodes: ActionSectionNode[], targetId: string, acc: Set<string>): boolean {
        for (const n of nodes) {
            if (n.id === targetId) return true
            if (expandParents(n.children, targetId, acc)) {
                acc.add(n.id)
                return true
            }
        }
        return false
    }

    async function selectSection(id: string | 'all'): Promise<void> {
        activeSection.value = id
        if (id === 'all') {
            categoryFilterIds.value = []
            return
        }
        const next = new Set(expandedIds.value)
        next.add(id)
        expandParents(sectionTree.value, id, next)
        expandedIds.value = next
        const node = sections.value.find(s => s.id === id)
        if (node && node.children_count > 0) await loadByParent(id)
        categoryFilterIds.value = [id]
    }

    function countInSection(id: string): number {
        return sections.value.find(s => s.id === id)?.children_count ?? 0
    }

    function addSection(section: ActionCategory): void {
        if (!sections.value.some(s => s.id === section.id)) {
            sections.value = [...sections.value, section]
        }
        if (section.parent_id !== null) {
            loadedParents.value = new Set([...loadedParents.value, section.parent_id])
        }
    }

    function updateSection(updated: ActionCategory): void {
        const idx = sections.value.findIndex(s => s.id === updated.id)
        if (idx !== -1) {
            const next = [...sections.value]
            next[idx] = updated
            sections.value = next
        }
    }

    function removeSection(id: string): void {
        sections.value = sections.value.filter(s => s.id !== id)
    }

    function flatten(nodes: ActionSectionNode[], depth: number): FlatActionSectionItem[] {
        const result: FlatActionSectionItem[] = []
        for (const n of nodes) {
            const hasChildren = n.children_count > 0
            result.push({section: n, depth, hasChildren})
            if (n.children.length && expandedIds.value.has(n.id)) {
                result.push(...flatten(n.children, depth + 1))
            }
        }
        return result
    }

    return {
        sections,
        loading,
        activeSection,
        expandedIds,
        categoryFilterIds,
        sectionTree,
        sidebarItems,
        allSectionsFlat,
        currentSectionName,
        toggleExpand,
        selectSection,
        countInSection,
        addSection,
        updateSection,
        removeSection,
    }
}
