<script setup lang="ts">
import AppShell from '@/layouts/AppShell.vue'
import PageHeader from '@/components/PageHeader.vue'
import SearchInput from '@/components/SearchInput.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import ScenariosSidebar from './components/ScenariosSidebar.vue'
import ScenariosTable from './components/ScenariosTable.vue'
import SectionFormDialog from './components/SectionFormDialog.vue'
import ScenarioCreateFormDialog from './components/ScenarioCreateFormDialog.vue'

import {useDashboardNavigation} from '@/composables/useDashboardNavigation'
import {useScenarioSectionTree, type ScenarioSectionNode} from '@/modules/scenario/composables/useScenarioSectionTree'
import {useScenarioSectionModal} from '@/modules/scenario/composables/useScenarioSectionModal'
import {useScenarioCreateModal} from '@/modules/scenario/composables/useScenarioCreateModal'
import {useScenarioFeed, type StatusTab} from '@/modules/scenario/composables/useScenarioFeed'
import {usePlayScenario} from '@/modules/scenario/composables/usePlayScenario'
import {useAuthStore} from '@/stores/auth'

import {scenarioRepository} from '@/modules/scenario/repositories/scenarioRepository'
import {scenarioCategoryRepository} from '@/modules/scenario/repositories/scenarioCategoryRepository'
import {groupRepository} from '@/modules/groups/repositories/groupRepository'
import type {FeedScenarioItem, FeedFolderItem} from '@/modules/scenario/repositories/scenarioFeedRepository'
import type {CategoryRef} from '@/modules/scenario/repositories/categoryRepository'

import {Head, router} from '@inertiajs/vue3'
import {ChevronRight, FolderPlus, Plus} from 'lucide-vue-next'
import {computed, ref} from 'vue'
import {toast} from 'vue-sonner'
import {pluralRu} from '@/lib/pluralize'

const {navigationItems} = useDashboardNavigation()
const auth = useAuthStore()
const canManage = computed(() => auth.hasPermission('scenario_create'))
const canDelete = computed(() => auth.hasPermission('scenario_delete'))

// ── Tree composable ──────────────────────────────────────────────────────
const tree = useScenarioSectionTree()
const activeFolder = tree.activeSection

const {
  rows, meta, countsByStatus, loading,
  page, search: searchQuery, statusTab, load: loadFeed,
} = useScenarioFeed(activeFolder)

// ── Section CRUD modal ───────────────────────────────────────────────────
const sectionModal = useScenarioSectionModal(
    (created) => {
      tree.addSection(created);
      tree.selectSection(created.id)
    },
    (updated) => {
      tree.updateSection(updated)
    },
)

async function loadGroups(query: string): Promise<Array<Record<string, unknown>>> {
  const qs = new URLSearchParams({'page[size]': '20'})
  if (query) qs.set('filter[search]', query)
  const res = await groupRepository.list(qs)
  return res.data.map(g => ({id: g.id, name: g.name}))
}

// ── Section delete ───────────────────────────────────────────────────────
const deleteTarget = ref<CategoryRef | null>(null)
const deleteLoading = ref(false)
const deleteError = ref<string | null>(null)

function confirmDeleteSection(section: CategoryRef): void {
  deleteTarget.value = section
  deleteError.value = null
}

async function doDeleteSection(): Promise<void> {
  if (!deleteTarget.value) return
  deleteLoading.value = true
  deleteError.value = null
  try {
    await scenarioCategoryRepository.remove(deleteTarget.value.id)
    tree.removeSection(deleteTarget.value.id)
    if (activeFolder.value === deleteTarget.value.id) selectFolder('all')
    deleteTarget.value = null
    toast.success('Раздел удалён')
  } catch (e: unknown) {
    deleteError.value = e instanceof Error ? e.message : 'Не удалось удалить раздел.'
    toast.error(deleteError.value)
  } finally {
    deleteLoading.value = false
  }
}

// ── Scenario delete / duplicate ──────────────────────────────────────────
const deleteScenarioTarget = ref<{ id: string; name: string } | null>(null)
const deleteScenarioLoading = ref(false)

function confirmDeleteScenario(s: FeedScenarioItem): void {
  deleteScenarioTarget.value = {id: s.id, name: s.name}
}

async function doDeleteScenario(): Promise<void> {
  if (!deleteScenarioTarget.value) return
  deleteScenarioLoading.value = true
  try {
    await scenarioRepository.remove(deleteScenarioTarget.value.id)
    deleteScenarioTarget.value = null
    toast.success('Сценарий удалён')
    await loadFeed()
  } catch (e: unknown) {
    toast.error(e instanceof Error ? e.message : 'Не удалось удалить сценарий.')
  } finally {
    deleteScenarioLoading.value = false
  }
}

async function duplicateScenario(id: string): Promise<void> {
  try {
    const created = await scenarioRepository.duplicate(id)
    toast.success('Сценарий дублирован')
    router.visit(route('scenarios.edit', created.id))
  } catch (e: unknown) {
    toast.error(e instanceof Error ? e.message : 'Не удалось дублировать сценарий.')
  }
}

// ── Scenario create ──────────────────────────────────────────────────────
const createModal = useScenarioCreateModal((created) => {
  router.visit(route('scenarios.edit', created.id))
})

function openCreateScenario(): void {
  const preset = activeFolder.value !== 'all' ? [activeFolder.value] : []
  createModal.openCreate(preset)
}

// ── UI ───────────────────────────────────────────────────────────────────
const isSearchMode = computed(() => searchQuery.value.trim().length > 0)

async function selectFolder(id: string): Promise<void> {
  statusTab.value = 'all'
  searchQuery.value = ''
  page.value = 1
  await tree.selectSection(id === 'all' ? 'all' : id)
}

const {launch: launchScenario} = usePlayScenario()

function playScenario(id: string): void {
  launchScenario({scenarioId: id})
}

function openScenario(id: string): void {
  router.visit(route('scenarios.edit', id))
}

const currentFolderTitle = computed(() => {
  if (activeFolder.value === 'all') return 'Сценарии'
  return tree.currentSectionName.value
})

const headerSubtitle = computed(() => {
  const items = meta.value.items_total
  const folders = meta.value.folders_total
  return [
    `${folders} ${pluralRu(folders, ['раздел', 'раздела', 'разделов'])}`,
    `${items} ${pluralRu(items, ['сценарий', 'сценария', 'сценариев'])}`,
  ].join(' · ')
})

// ── Breadcrumb ───────────────────────────────────────────────────────────
interface BreadcrumbItem {
  id: string;
  name: string
}

const breadcrumb = computed<BreadcrumbItem[]>(() => {
  const root: BreadcrumbItem = {id: 'all', name: 'Сценарии'}
  if (activeFolder.value === 'all') return [root]

  function findPath(nodes: ScenarioSectionNode[], target: string, path: BreadcrumbItem[]): BreadcrumbItem[] | null {
    for (const n of nodes) {
      const curr: BreadcrumbItem = {id: n.id, name: n.name}
      if (n.id === target) return [...path, curr]
      const r = findPath(n.children, target, [...path, curr])
      if (r) return r
    }
    return null
  }

  return findPath(tree.sectionTree.value, activeFolder.value, [root])
      ?? [root, {id: activeFolder.value, name: tree.sections.value.find(s => s.id === activeFolder.value)?.name ?? '—'}]
})

const statusTabs = computed(() => [
  {key: 'all' as StatusTab, label: 'Все', count: countsByStatus.value.all},
  {key: 'active' as StatusTab, label: 'Активные', count: countsByStatus.value.active},
  {key: 'draft' as StatusTab, label: 'Черновики', count: countsByStatus.value.draft},
  {key: 'archived' as StatusTab, label: 'Архив', count: countsByStatus.value.archived},
])

// ── Mapping table events to handlers ─────────────────────────────────────
function onEditFolderRow(row: FeedFolderItem): void {
  sectionModal.openEdit({
    id: row.id, parent_id: row.parent_id, name: row.name,
    is_active: true, children_count: row.children_count,
  } as CategoryRef)
}

function onDeleteFolderRow(row: FeedFolderItem): void {
  confirmDeleteSection({
    id: row.id, parent_id: row.parent_id, name: row.name,
    is_active: true, children_count: row.children_count,
  } as CategoryRef)
}
</script>

<template>
  <Head title="Scenarios"/>

  <AppShell title="Scenarios" :navigation-items="navigationItems" flush>
    <div class="flex h-full min-h-0 w-full overflow-hidden">
      <ScenariosSidebar
          :tree="tree"
          :active-folder="activeFolder"
          :can-manage="canManage"
          :can-delete="canDelete"
          @select="selectFolder"
          @edit-section="sectionModal.openEdit($event)"
          @delete-section="confirmDeleteSection"
      />

      <!-- Main content -->
      <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
        <div class="flex-1 overflow-y-auto bg-white">
          <div class="px-8 pt-6">
            <!-- Breadcrumb -->
            <nav class="mb-3 flex items-center gap-1.5 text-[13px]">
              <span class="text-slate-500">Scenarios</span>
              <template v-for="(crumb, i) in breadcrumb" :key="crumb.id">
                <ChevronRight :size="12" class="text-slate-300"/>
                <button
                    class="transition-colors"
                    :class="i === breadcrumb.length - 1 ? 'font-medium text-slate-900' : 'text-slate-500 hover:text-slate-900'"
                    @click="selectFolder(crumb.id)"
                >{{ crumb.name }}
                </button>
              </template>
            </nav>

            <PageHeader :title="isSearchMode ? 'Результаты поиска' : currentFolderTitle" :subtitle="headerSubtitle">
              <template #actions>
                <button
                    v-if="canManage"
                    type="button"
                    class="inline-flex h-9 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-[13px] font-medium text-slate-700 shadow-sm transition-colors hover:bg-slate-50"
                    @click="sectionModal.openModal(activeFolder !== 'all' ? activeFolder : null)"
                >
                  <FolderPlus :size="14"/>
                  {{ activeFolder !== 'all' ? 'Новый подраздел' : 'Новый раздел' }}
                </button>
                <button
                    type="button"
                    class="inline-flex h-9 items-center gap-2 rounded-xl bg-blue-600 px-4 text-[13px] font-medium text-white shadow-sm transition-colors hover:bg-blue-700"
                    @click="openCreateScenario"
                >
                  <Plus :size="14"/>
                  Новый сценарий
                </button>
              </template>
            </PageHeader>

            <div class="mb-0 pb-3">
              <SearchInput v-model="searchQuery" placeholder="Поиск по сценариям..."/>
            </div>

            <div v-if="countsByStatus.all > 0 || isSearchMode"
                 class="flex items-center gap-4 border-b border-slate-200">
              <button
                  v-for="t in statusTabs"
                  :key="t.key"
                  class="relative flex h-10 items-center gap-2 text-[14px] font-semibold transition-colors"
                  :class="statusTab === t.key ? 'text-slate-900' : 'text-slate-500 hover:text-slate-700'"
                  @click="statusTab = t.key"
              >
                {{ t.label }}
                <span
                    class="inline-flex h-5 items-center rounded px-1.5 text-[11px] font-semibold tabular-nums"
                    :class="statusTab === t.key ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-500'"
                >{{ t.count }}</span>
                <span v-if="statusTab === t.key"
                      class="absolute -bottom-px left-0 right-0 h-0.5 rounded-full bg-blue-600"/>
              </button>
            </div>
          </div>

          <div class="space-y-6 px-8 py-5">
            <ScenariosTable
                :rows="rows"
                :meta="meta"
                :loading="loading"
                :is-search-mode="isSearchMode"
                :can-manage="canManage"
                :can-delete="canDelete"
                @select-folder="selectFolder"
                @edit-section="onEditFolderRow"
                @delete-section="onDeleteFolderRow"
                @open-scenario="openScenario"
                @duplicate-scenario="duplicateScenario"
                @play-scenario="playScenario"
                @delete-scenario="confirmDeleteScenario"
                @page-change="(p) => page = p"
            />
          </div>
        </div>
      </div>
    </div>
  </AppShell>

  <SectionFormDialog :modal="sectionModal" :tree="tree" :load-groups="loadGroups"/>

  <ScenarioCreateFormDialog
      :modal="createModal"
      :current-section-name="tree.currentSectionName.value"
  />

  <ConfirmDialog
      :open="!!deleteScenarioTarget"
      title="Удалить сценарий?"
      :loading="deleteScenarioLoading"
      @update:open="(v: boolean) => !v && (deleteScenarioTarget = null)"
      @confirm="doDeleteScenario"
  >
    Сценарий «{{ deleteScenarioTarget?.name }}» и все его версии будут удалены без возможности восстановления.
  </ConfirmDialog>

  <ConfirmDialog
      :open="!!deleteTarget"
      title="Удалить раздел?"
      :loading="deleteLoading"
      :error="deleteError"
      @update:open="(v: boolean) => !v && (deleteTarget = null)"
      @confirm="doDeleteSection"
  >
    «{{ deleteTarget?.name }}» будет удалён. Сценарии в нём не удаляются.
  </ConfirmDialog>
</template>
