<script setup lang="ts">
import AppShell from '@/layouts/AppShell.vue'
import ScenarioPlayer from '@/modules/scenario/components/player/ScenarioPlayer.vue'
import WorkspaceSidebar from './workspace/WorkspaceSidebar.vue'
import type {ScenarioRunPayload} from '@/modules/scenario/lib/scenario-player-types'
import {useDashboardNavigation} from '@/composables/useDashboardNavigation'
import {useStartScenarioListener} from '@/modules/scenario/composables/useStartScenarioListener'
import {scenarioRepository} from '@/modules/scenario/repositories/scenarioRepository'
import type {Scenario, ScenarioCategory} from '@/modules/scenario/types/scenario'
import {formatDateTime} from '@/lib/formatters'
import {Head, router} from '@inertiajs/vue3'
import {ClipboardList} from 'lucide-vue-next'
import {computed, onMounted, ref, watch} from 'vue'

const {navigationItems} = useDashboardNavigation()

// ── Types ─────────────────────────────────────────────────────────────

interface ScenarioItem {
  id: string
  name: string
  status: 'active' | 'draft' | 'archived'
}

const ROOT_KEY = '__root__'

function mapStatus(s: Scenario): ScenarioItem['status'] {
  if (!s.is_active) return 'archived'
  if (s.active_version_id) return 'active'
  return 'draft'
}

// ── Lazy tree state ──────────────────────────────────────────────────

// Children categories per parent. ROOT_KEY holds top-level categories.
const childrenByParent = ref<Map<string, ScenarioCategory[]>>(new Map())
// Scenarios per category. ROOT_KEY holds uncategorized scenarios.
const scenariosByCategory = ref<Map<string, ScenarioItem[]>>(new Map())
const expandedIds = ref(new Set<string>())
const loadingIds = ref(new Set<string>())
const rootLoading = ref(false)

async function loadCategoriesUnder(parentId: string | null): Promise<void> {
  const items = await scenarioRepository.categoriesByParent(parentId)
  const next = new Map(childrenByParent.value)
  next.set(parentId ?? ROOT_KEY, items)
  childrenByParent.value = next
}

async function loadScenariosIn(categoryId: string | null): Promise<void> {
  const qs = new URLSearchParams()
  qs.set('filter[active_only]', '0')
  qs.set('page[size]', '100')
  qs.set('filter[category_id]', categoryId ?? 'null')
  const page = await scenarioRepository.list(qs)
  const items: ScenarioItem[] = page.data.map(s => ({
    id: s.id,
    name: s.name,
    status: mapStatus(s),
  }))
  const next = new Map(scenariosByCategory.value)
  next.set(categoryId ?? ROOT_KEY, items)
  scenariosByCategory.value = next
}

async function loadRoot(): Promise<void> {
  rootLoading.value = true
  try {
    await Promise.all([loadCategoriesUnder(null), loadScenariosIn(null)])
  } finally {
    rootLoading.value = false
  }
}

async function expandFolder(catId: string): Promise<void> {
  const needCats = !childrenByParent.value.has(catId)
  const needScens = !scenariosByCategory.value.has(catId)
  if (!needCats && !needScens) return
  const nextLoading = new Set(loadingIds.value)
  nextLoading.add(catId)
  loadingIds.value = nextLoading
  try {
    await Promise.all([
      needCats ? loadCategoriesUnder(catId) : Promise.resolve(),
      needScens ? loadScenariosIn(catId) : Promise.resolve(),
    ])
  } finally {
    const done = new Set(loadingIds.value)
    done.delete(catId)
    loadingIds.value = done
  }
}

async function toggleExpand(catId: string): Promise<void> {
  if (expandedIds.value.has(catId)) {
    const next = new Set(expandedIds.value)
    next.delete(catId)
    expandedIds.value = next
    return
  }
  const next = new Set(expandedIds.value)
  next.add(catId)
  expandedIds.value = next
  await expandFolder(catId)
}

onMounted(() => {
  loadRoot()
})

// ── Sidebar tree ─────────────────────────────────────────────────────

interface FlatTreeItem {
  type: 'folder' | 'scenario'
  id: string
  depth: number
  hasChildren: boolean
  loading?: boolean
  folder?: ScenarioCategory
  scenario?: ScenarioItem
}

const sidebarTreeItems = computed<FlatTreeItem[]>(() => {
  const result: FlatTreeItem[] = []

  function walk(cats: ScenarioCategory[], depth: number) {
    for (const cat of cats) {
      const hasChildren = cat.children_count > 0 || (scenariosByCategory.value.get(cat.id)?.length ?? 1) > 0
      result.push({
        type: 'folder',
        id: cat.id,
        depth,
        folder: cat,
        hasChildren,
        loading: loadingIds.value.has(cat.id),
      })
      if (expandedIds.value.has(cat.id)) {
        const children = childrenByParent.value.get(cat.id) ?? []
        walk(children, depth + 1)
        const scens = scenariosByCategory.value.get(cat.id) ?? []
        for (const s of scens) {
          result.push({type: 'scenario', id: s.id, depth: depth + 1, hasChildren: false, scenario: s})
        }
      }
    }
  }

  walk(childrenByParent.value.get(ROOT_KEY) ?? [], 0)
  const rootScens = scenariosByCategory.value.get(ROOT_KEY) ?? []
  for (const s of rootScens) {
    result.push({type: 'scenario', id: s.id, depth: 0, hasChildren: false, scenario: s})
  }
  return result
})

// ── Search ───────────────────────────────────────────────────────────

interface SearchScenarioResult {
  id: string
  name: string
  status: ScenarioItem['status']
  folderId: string | null
}

const searchQuery = ref('')
const searchLoading = ref(false)
const searchScenarios = ref<SearchScenarioResult[]>([])
const allCategories = ref<ScenarioCategory[] | null>(null)
const searchAncestors = ref<ScenarioCategory[]>([])

const isSearchMode = computed(() => searchQuery.value.trim().length > 0)

async function ensureAllCategories(): Promise<void> {
  if (allCategories.value !== null) return
  allCategories.value = await scenarioRepository.categories()
}

async function runSearch(q: string): Promise<void> {
  searchLoading.value = true
  try {
    await ensureAllCategories()
    const qs = new URLSearchParams()
    qs.set('filter[search]', q)
    qs.set('filter[active_only]', '0')
    qs.set('page[size]', '100')
    const page = await scenarioRepository.list(qs)
    searchScenarios.value = page.data.map(s => ({
      id: s.id,
      name: s.name,
      status: mapStatus(s),
      folderId: s.categories[0]?.id ?? null,
    }))
    searchAncestors.value = page.includedCategories
  } finally {
    searchLoading.value = false
  }
}

let searchTimer: ReturnType<typeof setTimeout> | null = null
watch(searchQuery, (q) => {
  if (searchTimer) clearTimeout(searchTimer)
  const t = q.trim()
  if (!t) {
    searchScenarios.value = []
    searchAncestors.value = []
    return
  }
  searchTimer = setTimeout(() => {
    void runSearch(t)
  }, 250)
})

function findCategory(id: string): ScenarioCategory | undefined {
  return allCategories.value?.find(c => c.id === id)
      ?? searchAncestors.value.find(c => c.id === id)
      ?? childrenByParent.value.get(ROOT_KEY)?.find(c => c.id === id)
}

function pathFor(catId: string | null | undefined): string {
  if (!catId) return ''
  const cat = findCategory(catId)
  if (!cat) return ''
  const parts: string[] = [cat.name]
  let parentId = cat.parent_id
  while (parentId) {
    const parent = findCategory(parentId)
    if (!parent) break
    parts.unshift(parent.name)
    parentId = parent.parent_id
  }
  return parts.join(' / ')
}

function parentPathFor(catId: string | null | undefined): string {
  if (!catId) return ''
  const cat = findCategory(catId)
  if (!cat?.parent_id) return ''
  return pathFor(cat.parent_id)
}

const searchFolderResults = computed<ScenarioCategory[]>(() => {
  if (!isSearchMode.value || !allCategories.value) return []
  const words = searchQuery.value.trim().toLowerCase().split(/\s+/).filter(Boolean)
  if (!words.length) return []
  return allCategories.value.filter(c =>
      words.every(w => c.name.toLowerCase().includes(w)),
  )
})

function escapeHtml(text: string): string {
  return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')
}

function highlight(text: string, query: string): string {
  const safe = escapeHtml(text)
  const trimmed = query.trim()
  if (!trimmed) return safe
  const words = trimmed.split(/\s+/).map(w => w.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).filter(Boolean)
  if (!words.length) return safe
  const re = new RegExp(`(${words.join('|')})`, 'gi')
  return safe.replace(re, '<mark class="bg-amber-200 text-amber-900 rounded-[3px] not-italic">$1</mark>')
}

async function jumpToFolder(catId: string): Promise<void> {
  searchQuery.value = ''
  searchScenarios.value = []
  searchAncestors.value = []
  // Walk parent chain to expand all ancestors + the target folder
  const chain: string[] = []
  let curr: string | null = catId
  while (curr) {
    chain.unshift(curr)
    const cat = findCategory(curr)
    curr = cat?.parent_id ?? null
  }
  for (const id of chain) {
    if (!expandedIds.value.has(id)) {
      const next = new Set(expandedIds.value)
      next.add(id)
      expandedIds.value = next
      await expandFolder(id)
    }
  }
}

// ── Player state ──────────────────────────────────────────────────────

const selectedScenarioId = ref<string | null>(null)
const playerKey = ref(0)
const navigateOnRun = ref(false)
const activeRun = ref<ScenarioRunPayload | null>(null)

useStartScenarioListener((msg) => {
  router.visit(msg.url)
})

const STATUS_LABELS: Record<string, string> = {
  active: 'В процессе',
  completed: 'Завершён',
  failed: 'Ошибка',
}

const STATUS_PILL: Record<string, string> = {
  active: 'bg-blue-50 text-blue-700',
  completed: 'bg-emerald-50 text-emerald-700',
  failed: 'bg-red-50 text-red-600',
}

function pickScenario(id: string) {
  selectedScenarioId.value = id
  navigateOnRun.value = true
  activeRun.value = null
  playerKey.value++
}

function onRunUpdate(run: ScenarioRunPayload | null) {
  activeRun.value = run
  if (navigateOnRun.value && run?.id) {
    navigateOnRun.value = false
    router.visit(route('workspace.run', run.id))
  }
}
</script>

<template>
  <Head title="Сценарии"/>

  <AppShell title="Сценарии" :navigation-items="navigationItems" flush>
    <div class="flex h-full min-h-0 w-full overflow-hidden">
      <WorkspaceSidebar
          :search-query="searchQuery"
          :is-search-mode="isSearchMode"
          :search-loading="searchLoading"
          :root-loading="rootLoading"
          :search-folder-results="searchFolderResults"
          :search-scenarios="searchScenarios"
          :sidebar-tree-items="sidebarTreeItems"
          :expanded-ids="expandedIds"
          :selected-scenario-id="selectedScenarioId"
          :path-for="pathFor"
          :parent-path-for="parentPathFor"
          :highlight="highlight"
          @update:search-query="searchQuery = $event"
          @pick-scenario="pickScenario"
          @toggle-expand="toggleExpand"
          @jump-to-folder="jumpToFolder"
      />

      <!-- ══ Right panel ═══════════════════════════════════════════════ -->
      <div class="flex min-h-0 flex-1 flex-col overflow-hidden bg-slate-50">
        <!-- Empty state -->
        <div v-if="!selectedScenarioId" class="flex flex-1 flex-col items-center justify-center gap-4 text-center">
          <div
              class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white shadow-[0_1px_3px_rgba(15,23,42,0.08),0_0_0_1px_rgba(15,23,42,0.06)] text-slate-400">
            <ClipboardList :size="24"/>
          </div>
          <div>
            <div class="text-[15px] font-semibold text-slate-900">Выберите сценарий</div>
            <div class="mt-1 text-[13px] text-slate-500">Нажмите на сценарий в списке слева, чтобы запустить его</div>
          </div>
        </div>

        <!-- Player -->
        <template v-else>
          <!-- Session header -->
          <div class="shrink-0 border-b border-slate-200 bg-white px-6 py-4">
            <div class="flex items-start gap-4">
              <div class="min-w-0">
                <div class="flex items-center gap-2.5">
                  <span class="truncate text-[15px] font-bold text-slate-900">
                    {{ activeRun?.scenario_name ?? '…' }}
                  </span>
                  <span
                      v-if="activeRun?.status"
                      class="inline-flex h-5 shrink-0 items-center rounded-full px-2 text-[10px] font-semibold"
                      :class="STATUS_PILL[activeRun.status] ?? 'bg-slate-100 text-slate-500'"
                  >
                    {{ STATUS_LABELS[activeRun.status] ?? activeRun.status }}
                  </span>
                  <span
                      v-if="activeRun?.scenario_version_name"
                      class="inline-flex h-5 shrink-0 items-center rounded border border-slate-200 bg-white px-2 font-mono text-[10px] text-slate-400"
                  >
                    {{ activeRun.scenario_version_name }}
                  </span>
                </div>
                <div v-if="activeRun" class="mt-1.5 flex items-center gap-3 text-[12px] text-slate-400">
                  <span class="font-mono">{{ activeRun.id }}</span>
                  <template v-if="activeRun.created_at">
                    <span class="text-slate-300">·</span>
                    <span>{{ formatDateTime(activeRun.created_at) }}</span>
                  </template>
                </div>
              </div>
            </div>
          </div>

          <!-- Player area -->
          <div class="min-h-0 flex-1 overflow-y-auto p-6">
            <div class="mx-auto max-w-2xl">
              <ScenarioPlayer
                  :key="playerKey"
                  :scenario-id="selectedScenarioId"
                  @update:run="onRunUpdate"
              />
            </div>
          </div>
        </template>
      </div>
    </div>
  </AppShell>
</template>
