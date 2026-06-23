import { computed, onMounted, ref } from 'vue'
import { scenarioRepository } from '@/modules/scenario/repositories/scenarioRepository'
import type { ScenarioCategory } from '@/modules/scenario/types/scenario'

export interface ScenarioSectionNode extends ScenarioCategory {
  children: ScenarioSectionNode[]
}

export interface FlatScenarioSectionItem {
  section: ScenarioSectionNode
  depth: number
  hasChildren: boolean
}

export function useScenarioSectionTree() {
  const sections          = ref<ScenarioCategory[]>([])
  const loading           = ref(false)
  const loadedParents     = ref(new Set<string | null>())
  const activeSection     = ref<string | 'all'>('all')
  const expandedIds       = ref(new Set<string>())
  // Stable ref for scenario list filtering — only updated after children finish loading,
  // so the scenario list fires exactly one request per section click.
  const categoryFilterIds = ref<string[]>([])

  // ── Tree construction ────────────────────────────────────────────────

  const sectionTree = computed<ScenarioSectionNode[]>(() => {
    const map   = new Map<string, ScenarioSectionNode>()
    const roots: ScenarioSectionNode[] = []
    for (const c of sections.value) map.set(c.id, { ...c, children: [] })
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

  const sidebarItems = computed<FlatScenarioSectionItem[]>(() => flatten(sectionTree.value, 0))

  const allSectionsFlat = computed<{ id: string; name: string; depth: number }[]>(() => {
    const result: { id: string; name: string; depth: number }[] = []
    function walk(nodes: ScenarioSectionNode[], depth: number): void {
      for (const n of nodes) {
        result.push({ id: n.id, name: n.name, depth })
        if (n.children.length) walk(n.children, depth + 1)
      }
    }
    walk(sectionTree.value, 0)
    return result
  })

  const visibleSubsections = computed<ScenarioSectionNode[]>(() => {
    if (activeSection.value === 'all') return sectionTree.value
    const find = (nodes: ScenarioSectionNode[]): ScenarioSectionNode | undefined => {
      for (const n of nodes) {
        if (n.id === activeSection.value) return n
        const found = find(n.children)
        if (found) return found
      }
    }
    return find(sectionTree.value)?.children ?? []
  })

  const currentSectionName = computed(() =>
    activeSection.value === 'all'
      ? 'Все сценарии'
      : sections.value.find(c => c.id === activeSection.value)?.name ?? '—',
  )

  // ── Loading ──────────────────────────────────────────────────────────

  async function loadByParent(parentId: string | null): Promise<void> {
    if (loadedParents.value.has(parentId)) return
    loading.value = true
    try {
      const items = await scenarioRepository.categoriesByParent(parentId)
      const incoming = items.filter(c => !sections.value.some(s => s.id === c.id))
      sections.value = [...sections.value, ...incoming]
      loadedParents.value = new Set([...loadedParents.value, parentId])
    } catch { /* silent */ }
    finally { loading.value = false }
  }

  onMounted(() => loadByParent(null))

  // ── Expand / select ──────────────────────────────────────────────────

  async function toggleExpand(id: string): Promise<void> {
    if (expandedIds.value.has(id)) {
      const next = new Set(expandedIds.value)
      next.delete(id)
      expandedIds.value = next
      return
    }
    const node = sections.value.find(s => s.id === id)
    if (node && node.children_count > 0) {
      await loadByParent(id)
    }
    const next = new Set(expandedIds.value)
    next.add(id)
    expandedIds.value = next
  }

  function expandParents(nodes: ScenarioSectionNode[], targetId: string, acc: Set<string>): boolean {
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
    if (node && node.children_count > 0) {
      await loadByParent(id)
    }
    // Update filter once after loading — prevents double scenario request
    categoryFilterIds.value = [id]
  }

  // ── Counts ───────────────────────────────────────────────────────────

  function descendantIds(id: string): Set<string> {
    const ids = new Set<string>([id])
    function walk(nodes: ScenarioSectionNode[]): void {
      for (const n of nodes) {
        if (n.parent_id !== null && ids.has(n.parent_id)) ids.add(n.id)
        if (n.children.length) walk(n.children)
      }
    }
    for (let i = 0; i < 5; i++) walk(sectionTree.value)
    return ids
  }

  function countInSection(id: string): number {
    const node = sections.value.find(s => s.id === id)
    return node?.children_count ?? 0
  }

  // ── Mutations (for CRUD) ─────────────────────────────────────────────

  function addSection(section: ScenarioCategory): void {
    if (!sections.value.some(s => s.id === section.id)) {
      sections.value = [...sections.value, section]
    }
    if (section.parent_id !== null) {
      loadedParents.value = new Set([...loadedParents.value, section.parent_id])
    }
  }

  function updateSection(updated: ScenarioCategory): void {
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

  // ── Helpers ──────────────────────────────────────────────────────────

  function flatten(nodes: ScenarioSectionNode[], depth: number): FlatScenarioSectionItem[] {
    const result: FlatScenarioSectionItem[] = []
    for (const n of nodes) {
      const hasChildren = n.children_count > 0
      result.push({ section: n, depth, hasChildren })
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
    visibleSubsections,
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
