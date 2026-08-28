<script setup lang="ts">
import AppShell from '@/layouts/AppShell.vue'
import ScenarioPlayer from '@/modules/scenario/components/player/ScenarioPlayer.vue'
import WorkspaceSidebar from './workspace/WorkspaceSidebar.vue'
import type {ScenarioRunPayload} from '@/modules/scenario/lib/scenario-player-types'
import {useDashboardNavigation} from '@/composables/useDashboardNavigation'
import {useStartScenarioListener} from '@/modules/scenario/composables/useStartScenarioListener'
import {scenarioRepository} from '@/modules/scenario/repositories/scenarioRepository'
import type {FeedFolder, FeedScenario} from '@/modules/scenario/types/scenario-feed'
import {useAuthStore} from '@/stores/auth'
import {formatDateTime} from '@/lib/formatters'
import {Head, router} from '@inertiajs/vue3'
import {ClipboardList} from 'lucide-vue-next'
import {computed, onMounted, ref, watch} from 'vue'

const authStore = useAuthStore()
const projectId = ref<string | null>(authStore.user?.project_id ?? null)
const workspaceCategoryId = ref<string | null>(null)
const hasWorkspace = computed(() => workspaceCategoryId.value !== null)

const {navigationItems} = useDashboardNavigation()

interface ScenarioItem {
  id: string
  name: string
  status: 'active' | 'draft' | 'archived'
}

const childrenByParent = ref<Map<string, FeedFolder[]>>(new Map())
const scenariosByCategory = ref<Map<string, ScenarioItem[]>>(new Map())
const expandedIds = ref(new Set<string>())
const loadingIds = ref(new Set<string>())
const rootLoading = ref(false)

const pathById = new Map<string, string>()
const parentPathById = new Map<string, string>()
const pathIdsById = new Map<string, string[]>()

function recordFolders(folders: FeedFolder[]): void {
  for (const folder of folders) {
    pathById.set(folder.id, folder.parent_path ? `${folder.parent_path} / ${folder.name}` : folder.name)
    parentPathById.set(folder.id, folder.parent_path)
    if (folder.path_ids.length) pathIdsById.set(folder.id, folder.path_ids)
  }
}

function recordScenarioFolders(scenarios: FeedScenario[]): void {
  for (const scenario of scenarios) {
    if (scenario.folder_id && scenario.folder_path) pathById.set(scenario.folder_id, scenario.folder_path)
  }
}

async function loadNode(parentId: string): Promise<void> {
  const {folders, scenarios} = await scenarioRepository.feed({parentId, projectId: projectId.value})
  recordFolders(folders)

  const nextCats = new Map(childrenByParent.value)
  nextCats.set(parentId, folders)
  childrenByParent.value = nextCats

  const nextScens = new Map(scenariosByCategory.value)
  nextScens.set(parentId, scenarios.map(s => ({id: s.id, name: s.name, status: s.status})))
  scenariosByCategory.value = nextScens
}

async function loadRoot(): Promise<void> {
  const rootId = workspaceCategoryId.value
  if (rootId === null) return
  rootLoading.value = true
  try {
    await loadNode(rootId)
  } finally {
    rootLoading.value = false
  }
}

async function expandFolder(catId: string): Promise<void> {
  if (childrenByParent.value.has(catId)) return
  const nextLoading = new Set(loadingIds.value)
  nextLoading.add(catId)
  loadingIds.value = nextLoading
  try {
    await loadNode(catId)
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

onMounted(async () => {
  if (!authStore.user) await authStore.initialize()
  projectId.value = authStore.user?.project_id ?? null
  workspaceCategoryId.value = await scenarioRepository.workspaceCategoryId()
  await loadRoot()
})

interface FlatTreeItem {
  type: 'folder' | 'scenario'
  id: string
  depth: number
  hasChildren: boolean
  loading?: boolean
  folder?: FeedFolder
  scenario?: ScenarioItem
}

const sidebarTreeItems = computed<FlatTreeItem[]>(() => {
  const result: FlatTreeItem[] = []

  function walk(cats: FeedFolder[], depth: number) {
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

  const rootId = workspaceCategoryId.value
  if (rootId === null) return result
  walk(childrenByParent.value.get(rootId) ?? [], 0)
  const rootScens = scenariosByCategory.value.get(rootId) ?? []
  for (const s of rootScens) {
    result.push({type: 'scenario', id: s.id, depth: 0, hasChildren: false, scenario: s})
  }
  return result
})

interface SearchScenarioResult {
  id: string
  name: string
  status: ScenarioItem['status']
  folderId: string | null
}

const searchQuery = ref('')
const searchLoading = ref(false)
const searchScenarios = ref<SearchScenarioResult[]>([])
const searchFolders = ref<FeedFolder[]>([])

const isSearchMode = computed(() => searchQuery.value.trim().length > 0)

async function runSearch(q: string): Promise<void> {
  const rootId = workspaceCategoryId.value
  if (rootId === null) return
  searchLoading.value = true
  try {
    const {folders, scenarios} = await scenarioRepository.feed({rootId, search: q, projectId: projectId.value})
    recordFolders(folders)
    recordScenarioFolders(scenarios)
    searchFolders.value = folders
    searchScenarios.value = scenarios.map(s => ({id: s.id, name: s.name, status: s.status, folderId: s.folder_id}))
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
    searchFolders.value = []
    return
  }
  searchTimer = setTimeout(() => {
    void runSearch(t)
  }, 250)
})

function pathFor(catId: string | null | undefined): string {
  return catId ? (pathById.get(catId) ?? '') : ''
}

function parentPathFor(catId: string | null | undefined): string {
  return catId ? (parentPathById.get(catId) ?? '') : ''
}

const searchFolderResults = computed<FeedFolder[]>(() => searchFolders.value)

async function jumpToFolder(catId: string): Promise<void> {
  searchQuery.value = ''
  searchScenarios.value = []
  searchFolders.value = []
  const chain = pathIdsById.get(catId) ?? [catId]
  for (const id of chain) {
    if (id === workspaceCategoryId.value) continue
    if (!expandedIds.value.has(id)) {
      const next = new Set(expandedIds.value)
      next.add(id)
      expandedIds.value = next
      await expandFolder(id)
    }
  }
}

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
          :has-workspace="hasWorkspace"
          :search-folder-results="searchFolderResults"
          :search-scenarios="searchScenarios"
          :sidebar-tree-items="sidebarTreeItems"
          :expanded-ids="expandedIds"
          :selected-scenario-id="selectedScenarioId"
          :path-for="pathFor"
          :parent-path-for="parentPathFor"
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
