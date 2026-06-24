<script setup lang="ts">
import AppShell from '@/layouts/AppShell.vue'
import EmptyState from '@/components/EmptyState.vue'
import ListPagination from '@/components/ListPagination.vue'
import PageHeader from '@/components/PageHeader.vue'
import SearchInput from '@/components/SearchInput.vue'
import ActionEditorDrawer from './components/ActionEditorDrawer.vue'
import ActionFieldModal from './components/ActionFieldModal.vue'
import ActionRunModal from './components/ActionRunModal.vue'
import ActionScheduleModal from './components/ActionScheduleModal.vue'
import ActionsSidebar from './components/ActionsSidebar.vue'
import ActionSectionFormDialog from './components/ActionSectionFormDialog.vue'
import {useDashboardNavigation} from '@/composables/useDashboardNavigation'
import {useActionModal} from '@/modules/actions/composables/useActionModal'
import {useActionRunModal} from '@/modules/actions/composables/useActionRunModal'
import {useActionScheduleModal} from '@/modules/actions/composables/useActionScheduleModal'
import {useActionSectionTree} from '@/modules/actions/composables/useActionSectionTree'
import {useActionSectionModal} from '@/modules/actions/composables/useActionSectionModal'
import {useActionFeed} from '@/modules/actions/composables/useActionFeed'
import {actionCategoryRepository} from '@/modules/actions/repositories/actionCategoryRepository'
import {actionRepository} from '@/modules/actions/repositories/actionRepository'
import {actionTypeRepository} from '@/modules/actions/repositories/actionTypeRepository'
import type {Action, ActionCategory, ActionTypeMeta} from '@/modules/actions/types/action'
import type {FeedActionRow, FeedFolderRow} from '@/modules/actions/repositories/actionFeedRepository'
import {computed, ref} from 'vue'
import {Head} from '@inertiajs/vue3'
import {
  CalendarClock, ChevronRight, Clock3, Folder, MoreHorizontal, Pencil, Play, Plus,
  RefreshCw, Shield, ShieldOff, Trash2, Zap,
} from 'lucide-vue-next'
import {
  DropdownMenu, DropdownMenuContent, DropdownMenuItem,
  DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'

const {navigationItems} = useDashboardNavigation()

const tree = useActionSectionTree()
const feed = useActionFeed(tree.activeSection)

// Типы экшенов нужны только редактору (тип/конфиг-поля) — грузим лениво при
// первом создании/редактировании, а не на каждой загрузке страницы.
const actionTypes = ref<ActionTypeMeta[]>([])
let actionTypesLoaded = false

async function ensureActionTypes(): Promise<void> {
  if (actionTypesLoaded) return
  try {
    actionTypes.value = await actionTypeRepository.list()
    actionTypesLoaded = true
  } catch { /* silent */
  }
}

const modal = useActionModal(() => actionTypes.value, () => feed.load())
const runModal = useActionRunModal(() => feed.load())
const scheduleModal = useActionScheduleModal(() => feed.load())

const sectionModal = useActionSectionModal(
    (created: ActionCategory) => {
      tree.addSection(created)
      void tree.selectSection(created.id)
      feed.load()
    },
    (updated: ActionCategory) => {
      tree.updateSection(updated)
      feed.load()
    },
)

async function createInActiveSection(): Promise<void> {
  await ensureActionTypes()
  modal.openCreate(tree.activeSection.value !== 'all' ? tree.activeSection.value : null)
}

// ── Breadcrumb ──────────────────────────────────────────────────────────────
interface Crumb { id: string | 'all'; name: string }

const breadcrumb = computed<Crumb[]>(() => {
  const root: Crumb = {id: 'all', name: 'Действия'}
  if (tree.activeSection.value === 'all') return [root]
  const path: Crumb[] = []
  let curr = tree.sections.value.find(s => s.id === tree.activeSection.value)
  while (curr) {
    path.unshift({id: curr.id, name: curr.name})
    const parentId = curr.parent_id
    curr = parentId ? tree.sections.value.find(s => s.id === parentId) : undefined
  }
  return [root, ...path]
})

const headerTitle = computed(() =>
    tree.activeSection.value === 'all' ? 'Действия' : tree.currentSectionName.value,
)

// ── Folder rows ─────────────────────────────────────────────────────────────
function toCategory(row: FeedFolderRow): ActionCategory {
  return {
    id: row.id,
    parent_id: row.parent_id,
    name: row.name,
    is_active: true,
    is_system: row.is_system,
    children_count: row.children_count,
  }
}

function openFolder(row: FeedFolderRow): void {
  void tree.selectSection(row.id)
}

async function deleteSection(section: ActionCategory): Promise<void> {
  if (!window.confirm(`Удалить раздел «${section.name}»?`)) return
  await actionCategoryRepository.remove(section.id)
  tree.removeSection(section.id)
  if (tree.activeSection.value === section.id) await tree.selectSection('all')
  feed.load()
}

// ── Action rows (feed gives slim payload → догружаем полный по id) ───────────
async function editAction(row: FeedActionRow): Promise<void> {
  await ensureActionTypes()
  modal.openEdit(await actionRepository.find(row.id))
}

async function runAction(row: FeedActionRow): Promise<void> {
  runModal.open(await actionRepository.find(row.id))
}

async function scheduleAction(row: FeedActionRow): Promise<void> {
  scheduleModal.open(await actionRepository.find(row.id))
}

async function toggleAction(row: FeedActionRow): Promise<void> {
  const full = await actionRepository.find(row.id)
  if (await modal.toggleActive(full)) feed.load()
}

async function deleteAction(row: FeedActionRow): Promise<void> {
  if (await modal.remove({id: row.id, name: row.name} as Action)) feed.load()
}

function formatDate(value: string | null | undefined): string {
  if (!value) return '—'
  return new Intl.DateTimeFormat('ru-RU', {
    day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit',
  }).format(new Date(value))
}
</script>

<template>
  <Head title="Действия"/>

  <AppShell title="Действия" :navigation-items="navigationItems" flush>
    <div class="flex h-full min-h-0 w-full overflow-hidden">
      <ActionsSidebar
          :tree="tree"
          :active-folder="tree.activeSection.value"
          @select="(id: string) => tree.selectSection(id)"
          @edit-section="(s) => sectionModal.openEdit(s)"
          @delete-section="deleteSection"
      />

      <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
        <div class="flex-1 overflow-y-auto bg-slate-50">
          <div class="mx-auto max-w-6xl px-6 py-8">
            <!-- Breadcrumb -->
            <nav class="mb-3 flex items-center gap-1.5 text-[13px]">
              <template v-for="(crumb, i) in breadcrumb" :key="crumb.id">
                <ChevronRight v-if="i > 0" :size="12" class="text-slate-300"/>
                <button
                    class="transition-colors"
                    :class="i === breadcrumb.length - 1 ? 'font-medium text-slate-900' : 'text-slate-500 hover:text-slate-900'"
                    @click="tree.selectSection(crumb.id)"
                >{{ crumb.name }}
                </button>
              </template>
            </nav>

            <PageHeader
                :title="headerTitle"
                :subtitle="`${feed.meta.value.folders_total} разделов · ${feed.meta.value.items_total} действий`"
            >
              <template #actions>
                <button
                    class="inline-flex h-9 shrink-0 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-[13px] font-medium text-slate-700 transition hover:bg-slate-50"
                    @click="sectionModal.openModal(tree.activeSection.value !== 'all' ? tree.activeSection.value : null)">
                  <Plus :size="15"/>
                  Раздел
                </button>
                <a href="/actions/schedules"
                   class="inline-flex h-9 shrink-0 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-[13px] font-medium text-slate-700 transition hover:bg-slate-50">
                  <CalendarClock :size="15"/>
                  Расписания
                </a>
                <a href="/actions/runs"
                   class="inline-flex h-9 shrink-0 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-[13px] font-medium text-slate-700 transition hover:bg-slate-50">
                  <Clock3 :size="15"/>
                  История запусков
                </a>
                <button
                    class="inline-flex h-9 shrink-0 items-center gap-2 rounded-xl bg-blue-600 px-4 text-[13px] font-medium text-white shadow-sm transition hover:bg-blue-700"
                    @click="createInActiveSection">
                  <Plus :size="15"/>
                  Новый action
                </button>
              </template>
            </PageHeader>

            <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
              <div class="max-w-sm flex-1">
                <SearchInput v-model="feed.search.value" placeholder="Поиск по действиям..."/>
              </div>
              <button
                  class="inline-flex h-9 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-[13px] font-medium text-slate-600 transition hover:bg-slate-50"
                  :disabled="feed.loading.value" @click="feed.load()">
                <RefreshCw :size="14" :class="feed.loading.value ? 'animate-spin' : ''"/>
                Обновить
              </button>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
              <div class="grid border-b border-slate-100 px-5 py-3"
                   style="grid-template-columns: minmax(260px,1fr) 150px 150px 110px 40px">
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Название</div>
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Тип</div>
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Расписание</div>
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Статус</div>
                <div/>
              </div>

              <div v-if="feed.loading.value && !feed.rows.value.length"
                   class="px-5 py-8 text-center text-[13px] text-slate-400">
                Загрузка…
              </div>

              <EmptyState v-else-if="!feed.rows.value.length" title="Пусто"
                          subtitle="Создайте раздел или действие">
                <template #icon>
                  <Zap :size="20"/>
                </template>
              </EmptyState>

              <template v-else>
                <div
                    v-for="(row, idx) in feed.rows.value"
                    :key="`${row.type}-${row.id}`"
                    class="group grid items-center px-5 py-4 transition-colors hover:bg-slate-50/70"
                    :class="idx !== feed.rows.value.length - 1 ? 'border-b border-slate-100' : ''"
                    style="grid-template-columns: minmax(260px,1fr) 150px 150px 110px 40px"
                >
                  <!-- Folder row -->
                  <template v-if="row.type === 'folder'">
                    <button class="flex min-w-0 items-center gap-2 pr-4 text-left" @click="openFolder(row)">
                      <div class="flex h-7 w-7 flex-none items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                        <Folder :size="14"/>
                      </div>
                      <span class="truncate text-[13px] font-semibold text-slate-900">{{ row.name }}</span>
                    </button>
                    <div class="text-[12px] text-slate-400">Раздел</div>
                    <div/>
                    <div class="text-[11px] text-slate-400">{{ row.children_count }} внутри</div>
                    <div class="flex justify-end">
                      <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                          <button class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-slate-400 opacity-0 transition group-hover:opacity-100 hover:bg-slate-100 hover:text-slate-700">
                            <MoreHorizontal :size="15"/>
                          </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-48">
                          <DropdownMenuItem @click="sectionModal.openEdit(toCategory(row))">
                            <Pencil class="mr-2 h-4 w-4 text-slate-400"/>
                            Переименовать
                          </DropdownMenuItem>
                          <template v-if="!row.is_system">
                            <DropdownMenuSeparator/>
                            <DropdownMenuItem class="text-red-600 focus:bg-red-50 focus:text-red-600"
                                              @click="deleteSection(toCategory(row))">
                              <Trash2 class="mr-2 h-4 w-4"/>
                              Удалить
                            </DropdownMenuItem>
                          </template>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </div>
                  </template>

                  <!-- Action row -->
                  <template v-else>
                    <div class="min-w-0 pr-4">
                      <div class="flex items-center gap-2">
                        <div class="flex h-7 w-7 flex-none items-center justify-center rounded-lg text-[11px] font-bold text-white"
                             :class="row.is_active ? 'bg-blue-600' : 'bg-slate-300'">
                          <Zap :size="13"/>
                        </div>
                        <span class="truncate text-[13px] font-semibold text-slate-900">{{ row.name }}</span>
                      </div>
                      <div class="mt-0.5 flex min-w-0 items-center gap-2 pl-9">
                        <span class="truncate font-mono text-[11px] text-slate-400">{{ row.slug }}</span>
                        <span v-if="row.description" class="truncate text-[12px] text-slate-400">{{ row.description }}</span>
                      </div>
                    </div>

                    <div>
                      <span class="inline-flex h-6 items-center rounded-md bg-slate-100 px-2 text-[11px] font-semibold text-slate-600">
                        {{ row.action_type_label }}
                      </span>
                    </div>

                    <button class="min-w-0 text-left" @click="scheduleAction(row)">
                      <div v-if="row.schedule?.enabled" class="flex items-center gap-1.5 text-[12px] font-medium text-blue-700">
                        <CalendarClock :size="13"/>
                        <span class="font-mono">{{ row.schedule.cron ?? '—' }}</span>
                      </div>
                      <div v-else class="text-[12px] text-slate-300">—</div>
                      <div class="mt-0.5 truncate text-[11px] text-slate-400">
                        {{ row.schedule?.next_run_at ? formatDate(row.schedule.next_run_at) : 'Нет запуска' }}
                      </div>
                    </button>

                    <div>
                      <span class="inline-flex h-5 items-center rounded-full px-2 text-[11px] font-semibold"
                            :class="row.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-400'">
                        {{ row.is_active ? 'Активно' : 'Отключено' }}
                      </span>
                    </div>

                    <div class="flex justify-end">
                      <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                          <button class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-slate-400 opacity-0 transition group-hover:opacity-100 hover:bg-slate-100 hover:text-slate-700">
                            <MoreHorizontal :size="15"/>
                          </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-48">
                          <DropdownMenuItem @click="runAction(row)">
                            <Play class="mr-2 h-4 w-4 text-slate-400"/>
                            Запустить
                          </DropdownMenuItem>
                          <DropdownMenuItem @click="scheduleAction(row)">
                            <Clock3 class="mr-2 h-4 w-4 text-slate-400"/>
                            Расписание
                          </DropdownMenuItem>
                          <DropdownMenuItem @click="editAction(row)">
                            <Pencil class="mr-2 h-4 w-4 text-slate-400"/>
                            Редактировать
                          </DropdownMenuItem>
                          <DropdownMenuItem @click="toggleAction(row)">
                            <component :is="row.is_active ? ShieldOff : Shield" class="mr-2 h-4 w-4 text-slate-400"/>
                            {{ row.is_active ? 'Отключить' : 'Активировать' }}
                          </DropdownMenuItem>
                          <DropdownMenuSeparator/>
                          <DropdownMenuItem class="text-red-600 focus:bg-red-50 focus:text-red-600" @click="deleteAction(row)">
                            <Trash2 class="mr-2 h-4 w-4"/>
                            Удалить
                          </DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </div>
                  </template>
                </div>

                <ListPagination
                    v-model:current-page="feed.page.value"
                    :total-pages="feed.meta.value.last_page"
                    :total="feed.meta.value.total"
                    :per-page="feed.meta.value.per_page"
                />
              </template>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppShell>

  <ActionEditorDrawer :modal="modal" :action-types="actionTypes"/>
  <ActionFieldModal :modal="modal"/>
  <ActionRunModal :run-modal="runModal"/>
  <ActionScheduleModal :schedule-modal="scheduleModal"/>
  <ActionSectionFormDialog :modal="sectionModal" :tree="tree"/>
</template>
