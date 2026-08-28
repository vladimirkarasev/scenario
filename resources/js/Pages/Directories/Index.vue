<script setup lang="ts">
import AppShell from '@/layouts/AppShell.vue'
import EmptyState from '@/components/EmptyState.vue'
import PageHeader from '@/components/PageHeader.vue'
import SearchInput from '@/components/SearchInput.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import SectionSidebar from '@/components/sections/SectionSidebar.vue'
import SectionFormDialog from '@/components/sections/SectionFormDialog.vue'
import SectionTreeSelect from '@/components/sections/SectionTreeSelect.vue'
import {
  Dialog, DialogContent, DialogHeader, DialogTitle,
} from '@/components/ui/dialog'
import {
  DropdownMenu, DropdownMenuContent, DropdownMenuItem,
  DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {Label} from '@/components/ui/label'
import {
  FormActions, FormAutoSlug, FormBody, FormError, FormInput, FormSection, FormTextarea,
} from '@/components/form'
import {Skeleton} from '@/components/ui/skeleton'
import {useAuthStore} from '@/stores/auth'
import {useDashboardNavigation} from '@/composables/useDashboardNavigation'
import {useDirectoryFeed} from '@/modules/directories/composables/useDirectoryFeed'
import {directoryRepository} from '@/modules/directories/repositories/directoryRepository'
import {useDirectoryModal} from '@/modules/directories/composables/useDirectoryModal'
import {useDirectorySectionModal} from '@/modules/directories/composables/useDirectorySectionModal'
import {useDirectorySectionTree} from '@/modules/directories/composables/useDirectorySectionTree'
import {categoryRepository} from '@/modules/scenario/repositories/categoryRepository'
import type {CategoryRef} from '@/modules/scenario/types/category'
import {pluralRu} from '@/lib/pluralize'
import {Head, Link} from '@inertiajs/vue3'
import {
  ChevronRight,
  Database, Folder, FolderOpen, FolderPlus, MoreHorizontal,
  Pencil, Plus, RefreshCw, Trash2,
} from 'lucide-vue-next'
import {computed, ref} from 'vue'
import {toast} from 'vue-sonner'
import {formatDateTime} from '@/lib/formatters'
import {SOURCE_TYPES} from '@/modules/directories/sourceTypes'

const {navigationItems} = useDashboardNavigation()
const auth = useAuthStore()
const canManage = computed(() => auth.hasPermission('directory_create'))
const canDelete = computed(() => auth.hasPermission('directory_delete'))

const tree = useDirectorySectionTree()
const {rows, meta, loading, page, search: dirSearch, load: loadFeed} = useDirectoryFeed(tree.activeSection)
const dirModal = useDirectoryModal(() => void loadFeed())
const sectionModal = useDirectorySectionModal(
    (created) => {
      tree.addSection(created)
      tree.selectSection(created.id)
    },
    (updated) => {
      tree.updateSection(updated)
    },
)

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
    await categoryRepository.remove(deleteTarget.value.id)
    tree.removeSection(deleteTarget.value.id)
    if (tree.activeSection.value === deleteTarget.value.id) tree.selectSection('all')
    deleteTarget.value = null
    toast.success('Раздел удалён')
  } catch (e: unknown) {
    deleteError.value = e instanceof Error ? e.message : 'Не удалось удалить раздел.'
    toast.error(deleteError.value)
  } finally {
    deleteLoading.value = false
  }
}

function openAddDir(): void {
  dirModal.openCreate()
  if (tree.activeSection.value !== 'all') {
    dirModal.form.category_ids = [tree.activeSection.value]
  }
}

interface BreadcrumbItem {
  id: string;
  name: string
}

const breadcrumb = computed<BreadcrumbItem[]>(() => {
  const root: BreadcrumbItem = {id: 'all', name: 'Справочники'}
  if (tree.activeSection.value === 'all') return [root]

  const path: BreadcrumbItem[] = []
  let curr = tree.sections.value.find(s => s.id === tree.activeSection.value)
  while (curr) {
    path.unshift({id: curr.id, name: curr.name})
    curr = curr.parent_id
        ? tree.sections.value.find(s => s.id === curr!.parent_id)
        : undefined
  }
  return [root, ...path]
})

const isRoot = computed(() => tree.activeSection.value === 'all')

const headerSubtitle = computed(() => {
  const items = meta.value.items_total
  const folders = meta.value.folders_total
  const parts: string[] = []
  parts.push(`${folders} ${pluralRu(folders, ['раздел', 'раздела', 'разделов'])}`)
  parts.push(`${items} ${pluralRu(items, ['справочник', 'справочника', 'справочников'])}`)
  return parts.join(' · ')
})

async function editDirectory(id: string): Promise<void> {
  const {item} = await directoryRepository.find(id)
  dirModal.openEdit(item)
}

async function deleteDirectory(id: string): Promise<void> {
  const {item} = await directoryRepository.find(id)
  dirModal.openDeleteConfirm(item)
}
</script>

<template>
  <Head title="Справочники"/>

  <AppShell title="Справочники" :navigation-items="navigationItems" flush>
    <div class="flex h-full min-h-0 w-full overflow-hidden">
      <!-- ══ Sidebar ═══════════════════════════════════════════════════ -->
      <SectionSidebar
          :tree="tree"
          :active-folder="tree.activeSection.value"
          all-label="Справочники"
          title="Справочники"
          :title-icon="Database"
          :all-icon="Database"
          :can-edit="canManage"
          :can-delete="canDelete"
          @select="(id: string) => tree.selectSection(id)"
          @edit-section="(s) => sectionModal.openEdit(s)"
          @delete-section="confirmDeleteSection"
      />

      <!-- ══ Main content ══════════════════════════════════════════════ -->
      <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
        <div class="flex-1 overflow-y-auto bg-white">
          <!-- Header area -->
          <div class="px-8 pt-6">
            <!-- Breadcrumb -->
            <nav class="mb-3 flex items-center gap-1.5 text-[13px]">
              <span class="text-slate-500">Directories</span>
              <template v-for="(crumb, i) in breadcrumb" :key="crumb.id">
                <ChevronRight :size="12" class="text-slate-300"/>
                <button
                    class="transition-colors"
                    :class="i === breadcrumb.length - 1 ? 'font-medium text-slate-900' : 'text-slate-500 hover:text-slate-900'"
                    @click="tree.selectSection(crumb.id === 'all' ? 'all' : crumb.id)"
                >
                  {{ crumb.name }}
                </button>
              </template>
            </nav>

            <PageHeader
                :title="isRoot ? 'Справочники' : tree.currentSectionName.value"
                :subtitle="headerSubtitle"
            >
              <template #actions>
                <button
                    v-if="canManage"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-[13px] font-semibold text-slate-700 transition hover:bg-slate-50"
                    @click="sectionModal.openModal(tree.activeSection.value !== 'all' ? tree.activeSection.value : null)"
                >
                  <FolderPlus :size="15"/>
                  Создать раздел
                </button>
                <button
                    v-if="canManage"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3.5 py-2 text-[13px] font-semibold text-white transition hover:bg-blue-700"
                    @click="openAddDir"
                >
                  <Plus :size="15"/>
                  Создать справочник
                </button>
              </template>
            </PageHeader>

            <!-- Search row -->
            <div class="mb-5 flex flex-col gap-3 pb-3 md:flex-row md:items-center md:justify-between">
              <div class="max-w-sm flex-1">
                <SearchInput v-model="dirSearch" placeholder="Поиск по справочникам..."/>
              </div>
              <button
                  type="button"
                  class="inline-flex h-9 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-[13px] font-medium text-slate-600 transition hover:bg-slate-50"
                  :disabled="loading" @click="loadFeed()"
              >
                <RefreshCw :size="14" :class="loading ? 'animate-spin' : ''"/>
                Обновить
              </button>
            </div>

            <!-- Loading skeleton -->
            <div v-if="loading && !rows.length" class="overflow-hidden rounded-xl border border-slate-200 bg-white">
              <div class="grid border-b border-slate-100 px-5 py-3"
                   style="grid-template-columns: 1fr 160px 160px 108px">
                <Skeleton class="h-3 w-24"/>
                <Skeleton class="h-3 w-20"/>
                <Skeleton class="h-3 w-20"/>
                <Skeleton class="h-3 w-16"/>
              </div>
              <div
                  v-for="i in 6"
                  :key="i"
                  class="grid items-center border-b border-slate-100 px-5 py-4 last:border-0"
                  style="grid-template-columns: 1fr 160px 160px 108px"
              >
                <div class="flex items-center gap-3 pr-4">
                  <Skeleton class="h-8 w-8 flex-none rounded-lg"/>
                  <div class="min-w-0 flex-1 space-y-1.5">
                    <Skeleton class="h-3.5 w-40 rounded"/>
                    <Skeleton class="h-3 w-24 rounded"/>
                  </div>
                </div>
                <Skeleton class="h-3 w-24 rounded"/>
                <Skeleton class="h-3 w-24 rounded"/>
                <Skeleton class="h-7 w-7 rounded-full justify-self-end"/>
              </div>
            </div>

            <EmptyState
                v-else-if="!rows.length"
                title="Здесь пусто"
                :subtitle="dirSearch
                ? 'Ничего не найдено'
                : isRoot
                  ? 'Создайте раздел или справочник, чтобы начать'
                  : 'Создайте справочник в этом разделе'"
            >
              <template #icon>
                <Database :size="22"/>
              </template>
            </EmptyState>

            <!-- Unified table: folders + directories -->
            <div v-else class="overflow-hidden rounded-xl border border-slate-200 bg-white">
              <div
                  class="grid border-b border-slate-100 px-5 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-400"
                  style="grid-template-columns: 1fr 160px 160px 108px"
              >
                <div>Название</div>
                <div>Создано</div>
                <div>Отредактировано</div>
                <div class="text-right">Действия</div>
              </div>

              <template v-for="(row, idx) in rows" :key="`${row.type}-${row.id}`">
                <!-- Folder row -->
                <div
                    v-if="row.type === 'folder'"
                    class="group grid items-center px-5 py-3.5 transition-colors hover:bg-slate-50/70 cursor-pointer"
                    :class="idx < rows.length - 1 ? 'border-b border-slate-100' : ''"
                    style="grid-template-columns: 1fr 160px 160px 108px"
                    @click="tree.selectSection(row.id)"
                >
                  <div class="flex min-w-0 items-center gap-3 pr-4">
                    <div
                        class="flex h-8 w-8 flex-none items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                      <Folder :size="15"/>
                    </div>
                    <div class="min-w-0 flex-1">
                      <div
                          class="truncate text-[14px] font-semibold text-slate-900 group-hover:text-blue-600 transition-colors">
                        {{ row.name }}
                      </div>
                      <div class="mt-0.5 text-[11.5px] text-slate-400">
                        <template v-if="row.children_count">
                          {{ row.children_count }} {{
                            pluralRu(row.children_count, ['подпапка', 'подпапки', 'подпапок'])
                          }}
                        </template>
                        <template v-else>раздел</template>
                      </div>
                    </div>
                  </div>

                  <div class="text-[12.5px] text-slate-500 tabular-nums">{{ formatDateTime(row.created_at) }}</div>
                  <div class="text-[12.5px] text-slate-500 tabular-nums">{{ formatDateTime(row.updated_at) }}</div>

                  <div class="flex justify-end" @click.stop>
                    <DropdownMenu v-if="canManage || canDelete">
                      <DropdownMenuTrigger as-child>
                        <button
                            class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700">
                          <MoreHorizontal :size="15"/>
                        </button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent align="end" class="w-44">
                        <DropdownMenuItem class="cursor-pointer" @click="tree.selectSection(row.id)">
                          <FolderOpen class="mr-2 h-4 w-4 text-slate-400"/>
                          Открыть
                        </DropdownMenuItem>
                        <DropdownMenuItem v-if="canManage" class="cursor-pointer"
                                          @click="sectionModal.openEdit({ id: row.id, parent_id: row.parent_id, name: row.name, is_active: true, children_count: row.children_count } as CategoryRef)">
                          <Pencil class="mr-2 h-4 w-4 text-slate-400"/>
                          Переименовать
                        </DropdownMenuItem>
                        <DropdownMenuSeparator v-if="canDelete"/>
                        <DropdownMenuItem v-if="canDelete"
                                          class="cursor-pointer text-red-600 focus:bg-red-50 focus:text-red-600"
                                          @click="confirmDeleteSection({ id: row.id, parent_id: row.parent_id, name: row.name, is_active: true, children_count: row.children_count } as CategoryRef)">
                          <Trash2 class="mr-2 h-4 w-4"/>
                          Удалить
                        </DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </div>
                </div>

                <!-- Directory row -->
                <div
                    v-else
                    class="group grid items-center px-5 py-4 transition-colors hover:bg-slate-50/70"
                    :class="idx < rows.length - 1 ? 'border-b border-slate-100' : ''"
                    style="grid-template-columns: 1fr 160px 160px 108px"
                >
                  <div class="flex min-w-0 items-center gap-3 pr-4">
                    <div class="flex h-8 w-8 flex-none items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                      <Database :size="15"/>
                    </div>
                    <div class="min-w-0 flex-1">
                      <Link
                          :href="route('directories.show', row.id)"
                          class="truncate text-[14px] font-semibold text-slate-900 hover:text-blue-600 transition-colors"
                      >
                        {{ row.name }}
                      </Link>
                      <div class="mt-0.5 flex items-center gap-1.5">
                        <span class="font-mono text-[11px] text-slate-400">{{ row.slug }}</span>
                        <span v-if="row.versions_count > 0"
                              class="inline-flex h-4 items-center rounded bg-slate-100 px-1 font-mono text-[10px] text-slate-400">
                          v{{ row.versions_count }}
                        </span>
                      </div>
                      <p v-if="row.description" class="mt-0.5 truncate text-[12px] text-slate-400">{{
                          row.description
                        }}</p>
                    </div>
                  </div>

                  <div class="text-[12.5px] text-slate-500 tabular-nums">{{ formatDateTime(row.created_at) }}</div>
                  <div class="text-[12.5px] text-slate-500 tabular-nums">{{ formatDateTime(row.updated_at) }}</div>

                  <div class="flex justify-end">
                    <DropdownMenu v-if="canManage || canDelete">
                      <DropdownMenuTrigger as-child>
                        <button
                            class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700">
                          <MoreHorizontal :size="15"/>
                        </button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent align="end" class="w-44">
                        <DropdownMenuItem v-if="canManage" class="cursor-pointer" @click="editDirectory(row.id)">
                          <Pencil class="mr-2 h-4 w-4 text-slate-400"/>
                          Редактировать
                        </DropdownMenuItem>
                        <DropdownMenuSeparator v-if="canManage && canDelete"/>
                        <DropdownMenuItem v-if="canDelete"
                                          class="cursor-pointer text-red-600 focus:bg-red-50 focus:text-red-600"
                                          @click="deleteDirectory(row.id)">
                          <Trash2 class="mr-2 h-4 w-4"/>
                          Удалить
                        </DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </div>
                </div>
              </template>
            </div>

            <!-- Pagination -->
            <div v-if="meta.last_page > 1" class="mt-5 flex items-center justify-between">
              <span class="text-[12px] text-slate-400">
                Страница {{ meta.current_page }} из {{ meta.last_page }} · {{ meta.total }}
              </span>
              <div class="flex items-center gap-1">
                <button
                    class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition disabled:opacity-30 hover:enabled:bg-slate-50"
                    :disabled="meta.current_page <= 1"
                    @click="page = meta.current_page - 1"
                >
                  <ChevronRight :size="14" class="rotate-180"/>
                </button>
                <button
                    v-for="p in meta.last_page"
                    :key="p"
                    class="flex h-8 w-8 items-center justify-center rounded-lg border text-[13px] font-medium transition"
                    :class="p === meta.current_page
                    ? 'border-blue-500 bg-blue-50 text-blue-700'
                    : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50'"
                    @click="page = p"
                >
                  {{ p }}
                </button>
                <button
                    class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition disabled:opacity-30 hover:enabled:bg-slate-50"
                    :disabled="meta.current_page >= meta.last_page"
                    @click="page = meta.current_page + 1"
                >
                  <ChevronRight :size="14"/>
                </button>
              </div>
            </div>
            <div class="h-8"/>
          </div>
        </div>
      </div>
    </div>
  </AppShell>

  <!-- ══ Dialog: Новый / редактировать справочник ══════════════════════ -->
  <Dialog v-model:open="dirModal.showModal.value">
    <DialogContent class="sm:max-w-md">
      <DialogHeader>
        <DialogTitle class="flex items-center gap-2">
          <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
            <Database :size="14"/>
          </div>
          {{ dirModal.editing.value ? 'Редактировать справочник' : 'Новый справочник' }}
        </DialogTitle>
      </DialogHeader>

      <form @submit.prevent="dirModal.save()" novalidate>
        <FormBody :padding="'sm'" :scrollable="false">
          <FormError :message="dirModal.formError.value"/>
          <FormSection>
            <FormInput
                v-model="dirModal.form.name"
                label="Название"
                placeholder="Например: Грейды сотрудников"
                required
                :error="dirModal.errors.name"
            />
            <FormAutoSlug
                v-model="dirModal.form.slug"
                :source="dirModal.form.name"
                label="Slug"
                placeholder="grades"
                required
                :auto-lock-on-edit="!!dirModal.editing.value"
                :error="dirModal.errors.slug"
            />
            <FormTextarea
                v-model="dirModal.form.description as string"
                label="Описание"
                placeholder="Краткое описание справочника..."
                :rows="2"
                :error="dirModal.errors.description"
                @update:model-value="(v: string) => dirModal.form.description = v || null"
            />

            <div v-if="dirModal.editing.value" class="space-y-1.5">
              <Label>Разделы</Label>
              <SectionTreeSelect
                  v-model="dirModal.form.category_ids"
                  :load-all="() => categoryRepository.list().then(r => r.items)"
                  :open="dirModal.showModal.value"
              />
            </div>

            <div class="space-y-1.5">
              <Label>Тип источника</Label>
              <div class="grid grid-cols-2 gap-2">
                <button
                    v-for="src in SOURCE_TYPES"
                    :key="src.id"
                    type="button"
                    class="flex flex-col items-start gap-1.5 rounded-xl border p-3 text-left transition"
                    :class="dirModal.form.source_type === src.id ? 'border-blue-400 bg-blue-50 ring-2 ring-blue-100' : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50'"
                    @click="dirModal.form.source_type = src.id"
                >
                  <component :is="src.icon" :size="14"
                             :class="dirModal.form.source_type === src.id ? 'text-blue-600' : 'text-slate-400'"/>
                  <div>
                    <div class="text-[12px] font-semibold"
                         :class="dirModal.form.source_type === src.id ? 'text-blue-700' : 'text-slate-700'">{{
                        src.label
                      }}
                    </div>
                    <div class="text-[11px] leading-tight text-slate-400">{{ src.description }}</div>
                  </div>
                </button>
              </div>
            </div>
          </FormSection>
        </FormBody>

        <FormActions
            :submitting="dirModal.submitting.value"
            :submit-label="dirModal.editing.value ? 'Сохранить' : 'Создать справочник'"
            @cancel="dirModal.close()"
            @submit="dirModal.save()"
        />
      </form>
    </DialogContent>
  </Dialog>

  <!-- ══ Dialog: Новый раздел ══════════════════════════════════════════ -->
  <SectionFormDialog
      :modal="sectionModal"
      :parent-options="tree.allSectionsFlat.value"
      :exclude-ids="sectionModal.editingId.value ? [...tree.descendantIds(sectionModal.editingId.value)] : []"
      placeholder="Например: HR"
  />
  <ConfirmDialog
      :open="!!deleteTarget"
      title="Удалить раздел?"
      :loading="deleteLoading"
      :error="deleteError"
      @update:open="(v: boolean) => !v && (deleteTarget = null)"
      @confirm="doDeleteSection()"
  >
    «{{ deleteTarget?.name }}» будет удалён. Справочники в нём не удаляются.
  </ConfirmDialog>

  <ConfirmDialog
      :open="!!dirModal.confirmDelete.value"
      title="Удалить справочник?"
      :loading="dirModal.deleting.value"
      :error="dirModal.deleteError.value"
      @update:open="(v: boolean) => !v && dirModal.closeDeleteConfirm()"
      @confirm="dirModal.doDelete()"
  >
    «{{ dirModal.confirmDelete.value?.name }}» будет удалён без возможности восстановления.
  </ConfirmDialog>
</template>
