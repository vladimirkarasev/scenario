import {computed, onMounted, ref, type Ref} from 'vue'
import type {SectionCategory, SectionNode, FlatSectionItem} from '@/types/section'

export interface SectionTreeConfig<T extends SectionCategory> {
    loadByParent: (parentId: string | null) => Promise<T[]>
    allLabel: string
}

export function useSectionTree<T extends SectionCategory>(config: SectionTreeConfig<T>) {
    const sections = ref([]) as Ref<T[]>
    const loading = ref(false)
    const loadedParents = ref(new Set<string | null>())
    const activeSection = ref<string | 'all'>('all')
    const expandedIds = ref(new Set<string>())
    const categoryFilterIds = ref<string[]>([])

    const sectionTree = computed<SectionNode<T>[]>(() => {
        const map = new Map<string, SectionNode<T>>()
        const roots: SectionNode<T>[] = []
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

    const sidebarItems = computed<FlatSectionItem<T>[]>(() => flatten(sectionTree.value, 0))

    const allSectionsFlat = computed<{ id: string; name: string; depth: number }[]>(() => {
        const result: { id: string; name: string; depth: number }[] = []

        function walk(nodes: SectionNode<T>[], depth: number): void {
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
            ? config.allLabel
            : sections.value.find(c => c.id === activeSection.value)?.name ?? '—',
    )

    async function loadByParent(parentId: string | null): Promise<void> {
        if (loadedParents.value.has(parentId)) return
        loading.value = true
        try {
            const items = await config.loadByParent(parentId)
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

    function expandParents(nodes: SectionNode<T>[], targetId: string, acc: Set<string>): boolean {
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

    function descendantIds(id: string): Set<string> {
        const ids = new Set<string>([id])

        function walk(nodes: SectionNode<T>[]): void {
            for (const n of nodes) {
                if (n.parent_id !== null && ids.has(n.parent_id)) ids.add(n.id)
                if (n.children.length) walk(n.children)
            }
        }

        for (let i = 0; i < 5; i++) walk(sectionTree.value)
        return ids
    }

    function countInSection(id: string): number {
        return sections.value.find(s => s.id === id)?.children_count ?? 0
    }

    function addSection(section: T): void {
        if (!sections.value.some(s => s.id === section.id)) {
            sections.value = [...sections.value, section]
        }
        if (section.parent_id !== null) {
            loadedParents.value = new Set([...loadedParents.value, section.parent_id])
        }
    }

    function updateSection(updated: T): void {
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

    function flatten(nodes: SectionNode<T>[], depth: number): FlatSectionItem<T>[] {
        const result: FlatSectionItem<T>[] = []
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
        descendantIds,
        countInSection,
        addSection,
        updateSection,
        removeSection,
    }
}

export type SectionTree<T extends SectionCategory = SectionCategory> = ReturnType<typeof useSectionTree<T>>
