<script setup lang="ts">
import AppShell from '@/layouts/AppShell.vue'
import EmptyState from '@/components/EmptyState.vue'
import ListPagination from '@/components/ListPagination.vue'
import PageHeader from '@/components/PageHeader.vue'
import {
  DropdownMenu, DropdownMenuContent, DropdownMenuItem,
  DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  FormActions, FormBody, FormError, FormInput, FormSection, FormToggle,
} from '@/components/form'
import {Button} from '@/components/ui/button'
import {Input} from '@/components/ui/input'
import {useDashboardNavigation} from '@/composables/useDashboardNavigation'
import {useProjectList} from '@/modules/projects/composables/useProjectList'
import {useProjectModal} from '@/modules/projects/composables/useProjectModal'
import {Head} from '@inertiajs/vue3'
import {
  Building2, Check, Copy, ExternalLink, Globe, Key,
  MoreHorizontal, Pencil, Plus, RefreshCw, Shield, ShieldOff, Trash2, X,
} from 'lucide-vue-next'
import {computed, ref} from 'vue'

const {navigationItems} = useDashboardNavigation()

const {page, loading, projects, meta, load} = useProjectList()
const {
  showModal, editing, form, errors, formError, submitting, modalLoading,
  openCreate, openEdit, close, save, toggleActive, regenerateSecret,
  confirmDelete, deleting, deleteError,
  openDeleteConfirm, closeDeleteConfirm, doDelete,
} = useProjectModal(load)

// ── UUID copy ──────────────────────────────────────────────────────────

const copiedId = ref<string | null>(null)

function copyId(id: string): void {
  navigator.clipboard.writeText(id).catch(() => {
  })
  copiedId.value = id
  setTimeout(() => {
    copiedId.value = null
  }, 1500)
}

function shortId(id: string): string {
  return id.slice(0, 8) + '…'
}

// ── Stats ──────────────────────────────────────────────────────────────

const activeCount = computed(() => projects.value.filter(p => p.is_active).length)
const inactiveCount = computed(() => projects.value.filter(p => !p.is_active).length)
</script>

<template>
  <Head title="Projects"/>

  <AppShell title="Projects" :navigation-items="navigationItems">
    <div class="min-h-full bg-slate-50">
      <div class="mx-auto max-w-5xl px-6 py-8">
        <!-- Header -->
        <PageHeader title="Проекты"
                    subtitle="Управление проектами для iframe-аутентификации. Каждый проект задаёт sitekey, host и shared secret для обмена JWT-токенами.">
          <template #actions>
            <button
                class="inline-flex h-9 shrink-0 items-center gap-2 rounded-xl bg-blue-600 px-4 text-[13px] font-medium text-white shadow-sm transition hover:bg-blue-700"
                @click="openCreate"
            >
              <Plus :size="15"/>
              Новый проект
            </button>
          </template>
        </PageHeader>

        <!-- Stats -->
        <div class="mb-6 grid grid-cols-3 gap-4">
          <div class="rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
            <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Всего</div>
            <div class="mt-1.5 text-3xl font-bold tabular-nums text-slate-900">{{ meta.total }}</div>
          </div>
          <div
              class="rounded-xl border border-emerald-100 bg-emerald-50 px-5 py-4 shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
            <div class="text-[11px] font-semibold uppercase tracking-wider text-emerald-600">Активных</div>
            <div class="mt-1.5 text-3xl font-bold tabular-nums text-emerald-700">{{ activeCount }}</div>
          </div>
          <div class="rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
            <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Отключённых</div>
            <div class="mt-1.5 text-3xl font-bold tabular-nums text-slate-400">{{ inactiveCount }}</div>
          </div>
        </div>

        <!-- Table -->
        <div
            class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
          <!-- Table header -->
          <div class="grid border-b border-slate-100 px-5 py-3"
               style="grid-template-columns: 1fr 140px 180px 100px 40px">
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Проект</div>
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Sitekey</div>
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Host</div>
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Статус</div>
            <div/>
          </div>

          <!-- Loading -->
          <div v-if="loading && !projects.length" class="px-5 py-8 text-center text-[13px] text-slate-400">
            Загрузка…
          </div>

          <!-- Empty -->
          <EmptyState v-else-if="!loading && !projects.length" title="Нет проектов"
                      subtitle="Создайте первый проект для настройки iframe-аутентификации">
            <template #icon>
              <Building2 :size="20"/>
            </template>
          </EmptyState>

          <!-- Rows -->
          <div
              v-for="(p, idx) in projects"
              :key="p.id"
              class="group relative grid items-center px-5 py-4 transition-colors hover:bg-slate-50/70"
              :class="idx !== projects.length - 1 ? 'border-b border-slate-100' : ''"
              style="grid-template-columns: 1fr 140px 180px 100px 40px"
          >
            <!-- Name + ID -->
            <div class="min-w-0 pr-4">
              <div class="flex items-center gap-2">
                <div
                    class="flex h-7 w-7 flex-none items-center justify-center rounded-lg text-white text-[11px] font-bold"
                    :class="p.is_active ? 'bg-blue-600' : 'bg-slate-300'"
                >
                  {{ p.name.slice(0, 1).toUpperCase() }}
                </div>
                <span class="truncate text-[13px] font-semibold text-slate-900">{{ p.name }}</span>
              </div>
              <button
                  class="mt-1 flex items-center gap-1 text-left font-mono text-[11px] text-slate-400 transition hover:text-slate-700"
                  :title="p.id"
                  @click="copyId(p.id)"
              >
                <component :is="copiedId === p.id ? Check : Copy" :size="10"/>
                {{ shortId(p.id) }}
              </button>
            </div>

            <!-- Sitekey -->
            <div class="min-w-0 pr-3">
              <span v-if="p.sitekey"
                    class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 font-mono text-[11px] text-slate-600">
                <Key :size="9"/>
                {{ p.sitekey }}
              </span>
              <span v-else class="text-[12px] text-slate-300">—</span>
            </div>

            <!-- Host -->
            <div class="min-w-0 pr-3">
              <span v-if="p.host" class="flex items-center gap-1 truncate text-[12px] text-slate-600">
                <Globe :size="11" class="flex-none text-slate-400"/>
                {{ p.host }}
              </span>
              <span v-else class="text-[12px] text-slate-300">—</span>
            </div>

            <!-- Status -->
            <div>
              <span
                  class="inline-flex h-5 items-center rounded-full px-2 text-[11px] font-semibold"
                  :class="p.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-400'"
              >
                {{ p.is_active ? 'Активен' : 'Отключён' }}
              </span>
            </div>

            <!-- Actions menu -->
            <div class="flex justify-end">
              <DropdownMenu>
                <DropdownMenuTrigger as-child>
                  <button
                      class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-slate-400 opacity-0 transition group-hover:opacity-100 hover:bg-slate-100 hover:text-slate-700">
                    <MoreHorizontal :size="15"/>
                  </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-44">
                  <DropdownMenuItem @click="openEdit(p)">
                    <Pencil class="mr-2 h-4 w-4 text-slate-400"/>
                    Редактировать
                  </DropdownMenuItem>
                  <DropdownMenuItem @click="toggleActive(p)">
                    <component :is="p.is_active ? ShieldOff : Shield" class="mr-2 h-4 w-4 text-slate-400"/>
                    {{ p.is_active ? 'Отключить' : 'Активировать' }}
                  </DropdownMenuItem>
                  <DropdownMenuSeparator/>
                  <DropdownMenuItem class="text-red-600 focus:text-red-600" @click="openDeleteConfirm(p)">
                    <Trash2 class="mr-2 h-4 w-4"/>
                    Удалить
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
          </div>

          <!-- Pagination -->
          <ListPagination
              v-model:current-page="page"
              :total-pages="meta.last_page"
              :total="meta.total"
              :per-page="meta.per_page"
          />
        </div>

        <!-- Footer note -->
        <div
            class="mt-4 flex items-start gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-[12px] text-slate-500 shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
          <ExternalLink :size="13" class="mt-0.5 flex-none text-slate-400"/>
          Один активный проект с корректными <code class="mx-0.5 rounded bg-slate-100 px-1 font-mono text-[11px]">sitekey
          + host + shared_secret</code> обязателен для обмена iframe-токенов.
        </div>
      </div>
    </div>
  </AppShell>

  <!-- ── Create / Edit modal ──────────────────────────────────────────── -->
  <Teleport to="body">
    <div
        v-if="showModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-sm"
        @click.self="close"
    >
      <div
          class="w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_24px_64px_-12px_rgba(15,23,42,0.2)]">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
          <div class="text-[15px] font-bold text-slate-900">{{
              editing ? 'Редактировать проект' : 'Новый проект'
            }}
          </div>
          <button class="text-slate-400 transition hover:text-slate-700" @click="close">
            <X :size="18"/>
          </button>
        </div>
        <div v-if="modalLoading" class="flex items-center justify-center py-10 text-[13px] text-slate-400">
          Загрузка…
        </div>

        <form v-else @submit.prevent="save" novalidate>
          <FormBody>
            <FormError :message="formError"/>
            <FormSection>
              <FormInput
                  v-model="form.name"
                  label="Название"
                  placeholder="Мой проект"
                  required
                  autocomplete="organization"
                  :error="errors.name"
              />
              <FormInput
                  v-model="form.sitekey"
                  label="Sitekey"
                  placeholder="my-site-key"
                  required
                  autocomplete="off"
                  :error="errors.sitekey"
              />
              <FormInput
                  v-model="form.host"
                  label="Host"
                  placeholder="example.com"
                  required
                  autocomplete="url"
                  :error="errors.host"
              />
              <div class="space-y-1.5">
                <label for="project-shared-secret" class="text-[12px] font-semibold text-slate-700">
                  Shared Secret <span class="text-red-500">*</span>
                </label>
                <div class="flex gap-2">
                  <Input
                      id="project-shared-secret"
                      name="shared_secret"
                      v-model="form.shared_secret"
                      autocomplete="off"
                      class="font-mono"
                      placeholder="supersecret"
                  />
                  <Button type="button" variant="outline" size="icon" title="Сгенерировать новый"
                          @click="regenerateSecret">
                    <RefreshCw :size="14"/>
                  </Button>
                </div>
                <p v-if="errors.shared_secret" class="text-[11px] text-red-600">{{ errors.shared_secret }}</p>
              </div>
              <FormToggle v-model="form.is_active" label="Активен"/>
            </FormSection>
          </FormBody>
          <FormActions
              :submitting="submitting"
              :submit-label="editing ? 'Сохранить' : 'Создать'"
              @cancel="close"
              @submit="save"
          />
        </form>
      </div>
    </div>
  </Teleport>

  <!-- ── Delete confirm ──────────────────────────────────────────────── -->
  <Teleport to="body">
    <div
        v-if="confirmDelete"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-sm"
        @click.self="closeDeleteConfirm"
    >
      <div
          class="w-full max-w-sm overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_24px_64px_-12px_rgba(15,23,42,0.2)]">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
          <div class="text-[15px] font-bold text-slate-900">Удалить проект</div>
          <button class="text-slate-400 transition hover:text-slate-700" @click="closeDeleteConfirm">
            <X :size="18"/>
          </button>
        </div>
        <div class="px-6 py-5 text-[13px] text-slate-600">
          Удалить проект <span class="font-semibold text-slate-900">{{ confirmDelete.name }}</span>? Это действие
          необратимо.
          <p v-if="deleteError" class="mt-2 text-red-600">{{ deleteError }}</p>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4">
          <button
              class="h-9 rounded-xl border border-slate-200 px-4 text-[13px] font-medium text-slate-600 transition hover:bg-slate-50"
              :disabled="deleting"
              @click="closeDeleteConfirm"
          >
            Отмена
          </button>
          <button
              class="h-9 rounded-xl bg-red-600 px-4 text-[13px] font-medium text-white transition hover:bg-red-700 disabled:opacity-40"
              :disabled="deleting"
              @click="doDelete"
          >
            {{ deleting ? 'Удаление…' : 'Удалить' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
