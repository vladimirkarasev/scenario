<script setup lang="ts">
import AppShell from '@/layouts/AppShell.vue'
import EmptyState from '@/components/EmptyState.vue'
import ListPagination from '@/components/ListPagination.vue'
import PageHeader from '@/components/PageHeader.vue'
import SearchInput from '@/components/SearchInput.vue'
import {
  FormActions, FormBody, FormError, FormField, FormInput, FormJsonInput, FormMockVariants,
  FormSection, FormSelect, FormTextarea, FormToggle,
} from '@/components/form'
import Combobox from '@/components/ui/combobox/Combobox.vue'
import SectionSidebar from '@/components/sections/SectionSidebar.vue'
import SectionFormDialog from '@/components/sections/SectionFormDialog.vue'
import SectionTreeSelect from '@/components/sections/SectionTreeSelect.vue'
import {useDashboardNavigation} from '@/composables/useDashboardNavigation'
import {useWebhookModal} from '@/modules/proxy/composables/useWebhookModal'
import {useProxyFeed} from '@/modules/proxy/composables/useProxyFeed'
import {useProxySectionTree} from '@/modules/proxy/composables/useProxySectionTree'
import {useProxySectionModal} from '@/modules/proxy/composables/useProxySectionModal'
import {proxyCategoryRepository} from '@/modules/proxy/repositories/proxyCategoryRepository'
import {webhookRepository} from '@/modules/proxy/repositories/webhookRepository'
import type {FeedEndpointRow, FeedFolderRow} from '@/modules/proxy/types/feed'
import type {ProxyCategory, WebhookEndpoint} from '@/modules/proxy/types/webhook'
import {Head, router} from '@inertiajs/vue3'
import {
  DropdownMenu, DropdownMenuContent, DropdownMenuItem,
  DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  Check, ChevronRight, Copy, FlaskConical, Folder, FolderPlus, FolderTree, KeyRound, List, Loader2, MoreHorizontal,
  Pencil, Plus, RefreshCw, Shield, ShieldOff, Trash2, X, Zap,
} from 'lucide-vue-next'
import {computed, ref} from 'vue'

const {navigationItems} = useDashboardNavigation()

const tree = useProxySectionTree()
const feed = useProxyFeed(tree.activeSection)
const {
  editing, showModal, saving, editError, errors, form,
  handlers, fields, loadingFields, receiveUrl,
  requiredCredentialType, availableConnections,
  openCreate, openEdit, close, save, remove, toggleActive,
} = useWebhookModal(() => feed.load())

const sectionModal = useProxySectionModal(
    (created: ProxyCategory) => {
      tree.addSection(created)
      void tree.selectSection(created.id)
      feed.load()
    },
    (updated: ProxyCategory) => {
      tree.updateSection(updated)
      feed.load()
    },
)

function createInActiveSection(): void {
  openCreate(tree.activeSection.value !== 'all' ? tree.activeSection.value : null)
}

function toCategory(row: FeedFolderRow): ProxyCategory {
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

async function deleteSection(section: ProxyCategory): Promise<void> {
  if (!window.confirm(`Удалить раздел «${section.name}»?`)) return
  await proxyCategoryRepository.remove(section.id)
  tree.removeSection(section.id)
  if (tree.activeSection.value === section.id) await tree.selectSection('all')
  feed.load()
}

// Лента отдаёт slim-строки → для редактирования/тоггла догружаем полную интеграцию по id.
async function editEndpoint(row: FeedEndpointRow): Promise<void> {
  openEdit(await webhookRepository.find(row.id))
}

async function toggleEndpoint(row: FeedEndpointRow): Promise<void> {
  await toggleActive(await webhookRepository.find(row.id))
  feed.load()
}

async function deleteEndpoint(row: FeedEndpointRow): Promise<void> {
  await remove({id: row.id, name: row.name} as WebhookEndpoint)
}

const copied = ref<string | null>(null)

async function copyText(text: string, key: string) {
  await navigator.clipboard.writeText(text).catch(() => {
  })
  copied.value = key
  setTimeout(() => {
    copied.value = null
  }, 1500)
}

const handlerItems = computed(() =>
  handlers.value.map(h => ({value: h.class, label: `${h.group}: ${h.label}`})),
)
</script>

<template>
  <Head title="Интеграции" />

  <AppShell title="Интеграции" :navigation-items="navigationItems" flush>
    <div class="flex h-full min-h-0 w-full overflow-hidden">
      <SectionSidebar
          :tree="tree"
          :active-folder="tree.activeSection.value"
          all-label="Все интеграции"
          title="Интеграции"
          :title-icon="Zap"
          :all-icon="Zap"
          @select="(id: string) => tree.selectSection(id)"
          @edit-section="(s) => sectionModal.openEdit(s)"
          @delete-section="deleteSection"
      >
        <template #top-links>
          <button
              class="group flex h-8 w-full items-center gap-2 rounded-lg px-1.5 text-[13px] text-slate-700 transition-colors hover:bg-slate-50"
              @click="router.visit('/proxy/connections')"
          >
            <span class="text-slate-400 transition-colors group-hover:text-slate-700">
              <KeyRound :size="14"/>
            </span>
            <span class="min-w-0 flex-1 truncate text-left">Доступы</span>
            <ChevronRight :size="12" class="flex-none text-slate-300"/>
          </button>
        </template>
      </SectionSidebar>

      <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
        <div class="flex-1 overflow-y-auto bg-white">
          <div class="px-8 pt-6">
        <nav class="mb-3 flex items-center gap-1.5 text-[13px]">
          <button
              class="transition-colors"
              :class="tree.activeSection.value === 'all' ? 'font-medium text-slate-900' : 'text-slate-500 hover:text-slate-900'"
              @click="tree.selectSection('all')"
          >Интеграции
          </button>
          <template v-if="tree.activeSection.value !== 'all'">
            <ChevronRight :size="12" class="text-slate-300" />
            <span class="font-medium text-slate-900">{{ tree.currentSectionName.value }}</span>
          </template>
        </nav>

        <PageHeader :title="tree.activeSection.value === 'all' ? 'Интеграции' : tree.currentSectionName.value"
                    :subtitle="`${feed.meta.value.folders_total} разделов · ${feed.meta.value.items_total} интеграций`">
          <template #actions>
            <button
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-[13px] font-semibold text-slate-700 transition hover:bg-slate-50"
                @click="sectionModal.openModal(tree.activeSection.value !== 'all' ? tree.activeSection.value : null)"
            >
              <FolderPlus :size="15"/>
              Создать раздел
            </button>
            <button
                class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3.5 py-2 text-[13px] font-semibold text-white transition hover:bg-blue-700"
                @click="createInActiveSection"
            >
              <Plus :size="15" />
              Создать интеграцию
            </button>
          </template>
        </PageHeader>

        <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div class="max-w-sm flex-1">
            <SearchInput v-model="feed.search.value" placeholder="Поиск по интеграциям..." />
          </div>
          <button
              class="inline-flex h-9 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-[13px] font-medium text-slate-600 transition hover:bg-slate-50"
              :disabled="feed.loading.value" @click="feed.load()">
            <RefreshCw :size="14" :class="feed.loading.value ? 'animate-spin' : ''" />
            Обновить
          </button>
        </div>

        <div v-if="feed.loading.value && !feed.rows.value.length"
             class="flex items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-white py-16 text-slate-400 shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
          <Loader2 :size="20" class="animate-spin" />
        </div>

        <EmptyState
            v-else-if="!feed.rows.value.length"
            title="Здесь пусто"
            :subtitle="feed.search.value
              ? 'Ничего не найдено'
              : tree.activeSection.value === 'all'
                ? 'Создайте раздел или интеграцию, чтобы начать'
                : 'Создайте интеграцию в этом разделе'"
        >
          <template #icon>
            <Zap :size="22" />
          </template>
        </EmptyState>

        <div v-else
            class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
          <div class="grid border-b border-slate-100 px-5 py-3" style="grid-template-columns: 1fr 180px 110px 40px">
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Интеграция</div>
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Code</div>
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Статус</div>
            <div />
          </div>

            <div
                v-for="(row, idx) in feed.rows.value"
                :key="`${row.type}-${row.id}`"
                class="group relative grid items-center px-5 py-4 transition-colors hover:bg-slate-50/70"
                :class="idx !== feed.rows.value.length - 1 ? 'border-b border-slate-100' : ''"
                style="grid-template-columns: 1fr 180px 110px 40px"
            >
              <!-- Раздел -->
              <template v-if="row.type === 'folder'">
                <button class="flex min-w-0 items-center gap-2 pr-4 text-left" @click="openFolder(row)">
                  <div class="flex h-7 w-7 flex-none items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                    <Folder :size="14" />
                  </div>
                  <span class="truncate text-[13px] font-semibold text-slate-900">{{ row.name }}</span>
                </button>
                <div class="text-[12px] text-slate-400">Раздел</div>
                <div class="text-[11px] text-slate-400">{{ row.children_count }} внутри</div>
                <div class="flex justify-end">
                  <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                      <button
                          class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-slate-400 opacity-0 transition group-hover:opacity-100 hover:bg-slate-100 hover:text-slate-700"
                          @click.stop>
                        <MoreHorizontal :size="15" />
                      </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-48">
                      <DropdownMenuItem @click.stop="sectionModal.openEdit(toCategory(row))">
                        <Pencil class="mr-2 h-4 w-4 text-slate-400" />
                        Переименовать
                      </DropdownMenuItem>
                      <template v-if="!row.is_system">
                        <DropdownMenuSeparator />
                        <DropdownMenuItem class="text-red-600 focus:bg-red-50 focus:text-red-600"
                                          @click.stop="deleteSection(toCategory(row))">
                          <Trash2 class="mr-2 h-4 w-4" />
                          Удалить
                        </DropdownMenuItem>
                      </template>
                    </DropdownMenuContent>
                  </DropdownMenu>
                </div>
              </template>

              <!-- Интеграция -->
              <template v-else>
                <div class="min-w-0 pr-4">
                  <div class="flex cursor-pointer items-center gap-2" @click="editEndpoint(row)">
                    <div
                        class="flex h-7 w-7 flex-none items-center justify-center rounded-lg text-[11px] font-bold text-white"
                        :class="row.is_active ? 'bg-blue-600' : 'bg-slate-300'"
                    >
                      {{ row.name.slice(0, 1).toUpperCase() }}
                    </div>
                    <span class="truncate text-[13px] font-semibold text-slate-900">{{ row.name }}</span>
                    <span
                        v-if="row.is_mocked"
                        class="inline-flex h-5 items-center gap-1 rounded-full bg-amber-50 px-2 text-[11px] font-semibold text-amber-700"
                        title="Возвращает мок-ответ вместо вызова обработчика"
                    >
                      <FlaskConical :size="11" /> Мок
                    </span>
                  </div>
                  <p class="mt-0.5 truncate pl-9 text-[12px] text-slate-400">{{ row.base_uri ?? row.description }}</p>
                </div>

                <div class="min-w-0 pr-3">
                  <span
                      class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 font-mono text-[11px] text-slate-600">
                    {{ row.code }}
                  </span>
                </div>

                <div>
                  <span
                      class="inline-flex h-5 items-center rounded-full px-2 text-[11px] font-semibold"
                      :class="row.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-400'"
                  >
                    {{ row.is_active ? 'Активна' : 'Отключена' }}
                  </span>
                </div>

                <div class="flex justify-end">
                  <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                      <button
                          class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                          @click.stop>
                        <MoreHorizontal :size="15" />
                      </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-48">
                      <DropdownMenuItem @click.stop="editEndpoint(row)">
                        <Pencil class="mr-2 h-4 w-4 text-slate-400" />
                        Редактировать
                      </DropdownMenuItem>
                      <DropdownMenuItem @click.stop="router.visit(`/proxy/requests?filter[endpoint_id]=${row.id}`)">
                        <List class="mr-2 h-4 w-4 text-slate-400" />
                        Запросы
                      </DropdownMenuItem>
                      <DropdownMenuItem @click.stop="toggleEndpoint(row)">
                        <component :is="row.is_active ? ShieldOff : Shield" class="mr-2 h-4 w-4 text-slate-400" />
                        {{ row.is_active ? 'Отключить' : 'Активировать' }}
                      </DropdownMenuItem>
                      <DropdownMenuSeparator />
                      <DropdownMenuItem class="text-red-600 focus:bg-red-50 focus:text-red-600"
                                        @click.stop="deleteEndpoint(row)">
                        <Trash2 class="mr-2 h-4 w-4" />
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
        </div>
          <div class="h-8" />
          </div>
        </div>
      </div>
    </div>
  </AppShell>

  <!-- ── Modal ──────────────────────────────────────────────────── -->
  <Teleport to="body">
    <div
        v-if="showModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-sm"
        @click.self="close"
    >
      <div
          class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_24px_64px_-12px_rgba(15,23,42,0.2)]">
        <div class="flex shrink-0 items-start justify-between border-b border-slate-100 px-6 py-4">
          <div class="min-w-0">
            <div class="text-[15px] font-bold text-slate-900">{{ editing ? editing.name : 'Новая интеграция' }}</div>
            <div class="mt-0.5 font-mono text-[11px] text-slate-400">{{ editing?.code ?? 'обработчик + доступы + mock' }}</div>
          </div>
          <button class="ml-4 shrink-0 text-slate-400 transition hover:text-slate-700" @click="close">
            <X :size="18" />
          </button>
        </div>

        <form class="flex-1 overflow-y-auto" novalidate @submit.prevent="save">
          <FormBody>
            <FormError :message="editError" />
            <FormSection>
              <FormInput v-model="form.name" label="Название" required :error="errors.name" />
              <FormInput v-model="form.code" label="Code" required :error="errors.code"
                         hint="Уникальный код интеграции" />
              <FormField label="Обработчик" required :error="errors.handler_class">
                <Combobox v-model="form.handler_class" :items="handlerItems"
                          placeholder="— выберите обработчик —" />
              </FormField>
              <FormSelect v-model="form.method" label="HTTP-метод" :error="errors.method">
                <option value="POST">POST</option>
                <option value="GET">GET</option>
              </FormSelect>
              <FormTextarea v-model="form.description" label="Описание" :rows="2" :error="errors.description" />
              <FormToggle v-model="form.is_active" label="Активна"
                          description="Интеграция принимает входящие запросы" />
            </FormSection>

            <template v-if="editing">
              <div class="border-t border-slate-100" />

              <div>
                <div class="mb-2.5 flex items-center gap-2 text-[12px] font-semibold text-slate-700">
                  <FolderTree :size="13" class="text-blue-600" />
                  Разделы
                </div>
                <SectionTreeSelect
                    v-model="form.category_ids"
                    :load-all="() => proxyCategoryRepository.all()"
                    :open="showModal"
                />
              </div>
            </template>

            <template v-if="requiredCredentialType">
              <div class="border-t border-slate-100" />
              <div>
                <div class="mb-2.5 flex items-center justify-between">
                  <div class="flex items-center gap-2 text-[12px] font-semibold text-slate-700">
                    <KeyRound :size="13" class="text-violet-600" />
                    Доступ
                  </div>
                  <a href="/proxy/connections" target="_blank"
                     class="text-[11px] font-medium text-blue-600 transition hover:text-blue-700">
                    Управление доступами →
                  </a>
                </div>
                <FormSelect
                    :model-value="form.connection_id === null ? '' : String(form.connection_id)"
                    label="Доступ к сервису"
                    :error="errors.connection_id"
                    @update:model-value="(v: string) => form.connection_id = v === '' ? null : Number(v)"
                >
                  <option value="">— без доступа</option>
                  <option v-for="c in availableConnections" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
                </FormSelect>
                <p v-if="!availableConnections.length" class="mt-1.5 text-[11px] text-slate-400">
                  Подходящих доступов нет.
                  <a href="/proxy/connections" target="_blank" class="text-blue-600 hover:text-blue-700">Создайте доступ</a>
                  и обновите страницу.
                </p>
              </div>
            </template>

            <template v-if="editing && receiveUrl">
              <div class="border-t border-slate-100" />
              <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-[12px]">
                <span class="w-24 shrink-0 text-slate-400">Приёмник</span>
                <span class="min-w-0 flex-1 truncate font-mono text-slate-700">{{ receiveUrl }}</span>
                <button type="button" class="shrink-0 text-slate-400 transition hover:text-slate-700"
                        @click="copyText(receiveUrl, 'receive')">
                  <Check v-if="copied === 'receive'" :size="13" class="text-emerald-500" />
                  <Copy v-else :size="13" />
                </button>
              </div>
            </template>

            <template v-if="loadingFields || fields.length">
              <div class="border-t border-slate-100" />
              <div>
                <div class="mb-2.5 text-[12px] font-semibold text-slate-700">Входные поля</div>
                <div v-if="loadingFields" class="flex items-center gap-2 py-2 text-[12px] text-slate-400">
                  <Loader2 :size="13" class="animate-spin" />
                  Загружаем поля…
                </div>
                <div v-else class="overflow-hidden rounded-xl border border-slate-200">
                  <div
                      v-for="(f, i) in fields"
                      :key="f.key"
                      class="grid items-center px-4 py-2 text-[12px]"
                      :class="i !== fields.length - 1 ? 'border-b border-slate-100' : ''"
                      style="grid-template-columns: 160px 1fr 80px"
                  >
                    <div class="flex items-center gap-1.5 font-mono text-slate-700">
                      {{ f.key }}
                      <span v-if="f.required"
                            class="rounded bg-blue-50 px-1 text-[10px] font-bold text-blue-600">req</span>
                    </div>
                    <div class="text-slate-600">{{ f.label }}</div>
                    <div>
                      <span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] text-slate-500">{{ f.type }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </template>

            <div class="border-t border-slate-100" />
            <FormJsonInput v-model="form.config" label="Config (JSON)" :rows="4" :error="errors.config" />

            <div class="border-t border-slate-100" />
            <div class="mb-2.5 flex items-center gap-2 text-[12px] font-semibold text-slate-700">
              <FlaskConical :size="13" class="text-amber-600" />
              Мок-ответы
            </div>
            <FormToggle v-model="form.is_mocked" label="Использовать мок-ответ"
                        description="При включении обработчик не вызывается — возвращается выбранный ответ." />
            <FormMockVariants v-model="form.mocks" :error="errors.mocks" />
          </FormBody>
        </form>
        <FormActions
            :submitting="saving"
            :submit-label="saving ? 'Сохраняем…' : 'Сохранить'"
            @cancel="close"
            @submit="save"
        />
      </div>
    </div>
  </Teleport>

  <SectionFormDialog
      :modal="sectionModal"
      :parent-options="tree.allSectionsFlat.value"
      :exclude-ids="sectionModal.editingId.value ? [...tree.descendantIds(sectionModal.editingId.value)] : []"
      placeholder="Например: CRM"
  />
</template>
