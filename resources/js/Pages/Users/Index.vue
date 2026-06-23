<script setup lang="ts">
import AppShell from '@/layouts/AppShell.vue'
import EmptyState from '@/components/EmptyState.vue'
import ListPagination from '@/components/ListPagination.vue'
import PageHeader from '@/components/PageHeader.vue'
import SearchInput from '@/components/SearchInput.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import UsersTabs from '@/modules/users/components/UsersTabs.vue'
import {
  DropdownMenu, DropdownMenuContent, DropdownMenuItem,
  DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  FormActions, FormBody, FormError, FormInput, FormPassword, FormRow, FormSection,
} from '@/components/form'
import { useDashboardNavigation } from '@/composables/useDashboardNavigation'
import { useUserFilters } from '@/modules/users/composables/useUserFilters'
import { useUserList } from '@/modules/users/composables/useUserList'
import { useUserModal } from '@/modules/users/composables/useUserModal'
import { useUserTokens } from '@/modules/users/composables/useUserTokens'
import { useAuthStore } from '@/stores/auth'
import { Head, Link } from '@inertiajs/vue3'
import { ChevronDown, Check, Copy, KeyRound, Layers, MoreHorizontal, Pencil, Plus, Shield, Trash2, Users, X } from 'lucide-vue-next'
import { computed, ref } from 'vue'

const { navigationItems } = useDashboardNavigation()

const auth = useAuthStore()
const canCreate = computed(() => auth.hasPermission('user_create'))
const canDelete = computed(() => auth.hasPermission('user_delete'))

const { params, search, page, loading, users, meta, load } = useUserList()

const {
  filterGroups, filterRoles, hasFilters,
  filterGroupSearch, filterRoleSearch,
  filterGroupOpen, filterRoleOpen,
  filterGroupResults, filterRoleResults,
  onGroupInput: onFilterGroupInput,
  onRoleInput: onFilterRoleInput,
  addGroup: addFilterGroup,
  removeGroup: removeFilterGroup,
  addRole: addFilterRole,
  removeRole: removeFilterRole,
  clear: clearFilters,
} = useUserFilters(params)

const {
  showModal, editing, form, errors, formError, submitting, modalLoading,
  selectedRoles, roleSearch, roleDropdownOpen, roleResults,
  selectedGroups, groupSearch, groupDropdownOpen, groupResults,
  onRoleInput, addRole, removeRole,
  onGroupInput, addGroup, removeGroup,
  openCreate, openEdit, close: closeModal, save,
  confirmDelete, deleting, deleteError,
  openDeleteConfirm, closeDeleteConfirm, doDelete: doDeleteUser,
} = useUserModal(load)

const {
  user: tokenUser, showModal: showTokensModal, tokens, loading: tokensLoading, error: tokensError,
  newTokenName, newTokenExpiresAt, creating: tokenCreating, createError: tokenCreateError, createdToken,
  revokingId,
  open: openTokens, close: closeTokens, create: createToken, revoke: revokeToken, dismissCreatedToken,
} = useUserTokens()

const copiedTokenId = ref<number | null>(null)

function copyToken(text: string, id: number): void {
  navigator.clipboard.writeText(text).then(() => {
    copiedTokenId.value = id
    setTimeout(() => { copiedTokenId.value = null }, 2000)
  })
}

function closeFilterGroupSoon(): void {
  setTimeout(() => {
    filterGroupOpen.value = false
    filterGroupSearch.value = ''
    filterGroupResults.value = []
  }, 150)
}

function closeFilterRoleSoon(): void {
  setTimeout(() => {
    filterRoleOpen.value = false
    filterRoleSearch.value = ''
    filterRoleResults.value = []
  }, 150)
}

function closeRoleDropdownSoon(): void {
  setTimeout(() => { roleDropdownOpen.value = false }, 150)
}

function closeGroupDropdownSoon(): void {
  setTimeout(() => { groupDropdownOpen.value = false }, 150)
}

function initials(name: string): string {
  return name.split(' ').slice(0, 2).map(s => s[0] ?? '').join('').toUpperCase()
}

function avatarColor(name: string): string {
  const colors = ['#2563EB', '#059669', '#7C3AED', '#DC2626', '#D97706', '#0891B2']
  let h = 0
  for (let i = 0; i < name.length; i++) h = (h * 31 + name.charCodeAt(i)) % colors.length
  return colors[Math.abs(h)]
}

function formatDate(iso: string | null): string {
  return iso ? iso.slice(0, 10) : '—'
}
</script>

<template>
  <Head title="Пользователи" />

  <AppShell title="Пользователи" :navigation-items="navigationItems">
    <div class="min-h-full bg-slate-50">
      <div class="mx-auto max-w-6xl px-6 py-8">
<PageHeader title="Пользователи и роли" subtitle="Управление пользователями, группами и ролями доступа.">
          <template #actions>
            <button
              v-if="canCreate"
              class="inline-flex h-9 shrink-0 items-center gap-2 rounded-xl bg-blue-600 px-4 text-[13px] font-medium text-white shadow-sm transition hover:bg-blue-700"
              @click="openCreate"
            >
              <Plus :size="15" /> Добавить пользователя
            </button>
          </template>
        </PageHeader>

        <!-- Stats -->
        <div class="mb-6 grid grid-cols-3 gap-4">
          <div class="rounded-xl border border-blue-300 bg-white px-5 py-4 shadow-[0_1px_2px_rgba(15,23,42,0.04)] ring-1 ring-blue-200">
            <div class="mb-1 flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
              <Users :size="11" /> Пользователи
            </div>
            <div class="text-3xl font-bold tabular-nums text-slate-900">{{ meta.total }}</div>
            <div class="mt-0.5 text-[11px] text-slate-400">в системе</div>
          </div>
          <Link href="/users/groups" class="rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-[0_1px_2px_rgba(15,23,42,0.04)] transition hover:border-blue-200">
            <div class="mb-1 flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
              <Layers :size="11" /> Группы
            </div>
            <div class="text-3xl font-bold tabular-nums text-slate-400">—</div>
            <div class="mt-0.5 text-[11px] text-slate-400">в системе</div>
          </Link>
          <Link href="/users/roles" class="rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-[0_1px_2px_rgba(15,23,42,0.04)] transition hover:border-blue-200">
            <div class="mb-1 flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
              <Shield :size="11" /> Роли
            </div>
            <div class="text-3xl font-bold tabular-nums text-slate-400">—</div>
            <div class="mt-0.5 text-[11px] text-slate-400">системных</div>
          </Link>
        </div>

        <!-- Tabs + search row -->
        <div class="mb-3 flex items-center justify-between gap-4">
          <UsersTabs active="users" />
          <div class="w-56">
            <SearchInput v-model="search" placeholder="Поиск пользователей..." />
          </div>
        </div>

        <!-- Filters -->
        <div class="mb-4 flex flex-wrap items-center gap-2">
          <!-- Groups filter -->
          <div class="relative">
            <button
              class="inline-flex h-8 items-center gap-1.5 rounded-lg border px-3 text-[12px] font-medium transition"
              :class="filterGroups.length ? 'border-blue-300 bg-blue-50 text-blue-700' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
              @click="filterGroupOpen = !filterGroupOpen; filterRoleOpen = false"
            >
              <Layers :size="12" />
              Группы
              <span v-if="filterGroups.length" class="flex h-4 w-4 items-center justify-center rounded-full bg-blue-600 text-[10px] font-bold text-white">{{ filterGroups.length }}</span>
              <ChevronDown :size="12" class="text-slate-400" />
            </button>
            <div
              v-if="filterGroupOpen"
              class="absolute left-0 top-full z-20 mt-1 w-64 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg"
            >
              <div class="border-b border-slate-100 px-3 py-2">
                <input
                  v-model="filterGroupSearch"
                  type="text"
                  class="h-7 w-full rounded-lg bg-slate-50 px-2.5 text-[12px] outline-none placeholder:text-slate-400 focus:ring-2 focus:ring-blue-100"
                  placeholder="Поиск группы..."
                  @input="onFilterGroupInput"
                  @blur="closeFilterGroupSoon"
                />
              </div>
              <div v-if="filterGroupResults.length" class="max-h-48 overflow-y-auto py-1">
                <button
                  v-for="g in filterGroupResults"
                  :key="g.id"
                  type="button"
                  class="flex w-full items-center gap-2 px-3 py-2 text-left text-[12px] text-slate-800 transition hover:bg-blue-50"
                  @mousedown.prevent="addFilterGroup(g)"
                >
                  <Layers :size="11" class="shrink-0 text-slate-400" />
                  {{ g.name }}
                </button>
              </div>
              <div v-else class="px-3 py-3 text-center text-[12px] text-slate-400">
                {{ filterGroupSearch ? 'Не найдено' : 'Введите название группы' }}
              </div>
            </div>
          </div>

          <!-- Roles filter -->
          <div class="relative">
            <button
              class="inline-flex h-8 items-center gap-1.5 rounded-lg border px-3 text-[12px] font-medium transition"
              :class="filterRoles.length ? 'border-blue-300 bg-blue-50 text-blue-700' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
              @click="filterRoleOpen = !filterRoleOpen; filterGroupOpen = false"
            >
              <Shield :size="12" />
              Роли
              <span v-if="filterRoles.length" class="flex h-4 w-4 items-center justify-center rounded-full bg-blue-600 text-[10px] font-bold text-white">{{ filterRoles.length }}</span>
              <ChevronDown :size="12" class="text-slate-400" />
            </button>
            <div
              v-if="filterRoleOpen"
              class="absolute left-0 top-full z-20 mt-1 w-64 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg"
            >
              <div class="border-b border-slate-100 px-3 py-2">
                <input
                  v-model="filterRoleSearch"
                  type="text"
                  class="h-7 w-full rounded-lg bg-slate-50 px-2.5 text-[12px] outline-none placeholder:text-slate-400 focus:ring-2 focus:ring-blue-100"
                  placeholder="Поиск роли..."
                  @input="onFilterRoleInput"
                  @blur="closeFilterRoleSoon"
                />
              </div>
              <div v-if="filterRoleResults.length" class="max-h-48 overflow-y-auto py-1">
                <button
                  v-for="r in filterRoleResults"
                  :key="r.id"
                  type="button"
                  class="flex w-full items-center gap-2 px-3 py-2 text-left text-[12px] text-slate-800 transition hover:bg-blue-50"
                  @mousedown.prevent="addFilterRole(r)"
                >
                  <Shield :size="11" class="shrink-0 text-slate-400" />
                  {{ r.title ?? r.name }}
                </button>
              </div>
              <div v-else class="px-3 py-3 text-center text-[12px] text-slate-400">
                {{ filterRoleSearch ? 'Не найдено' : 'Введите название роли' }}
              </div>
            </div>
          </div>

          <!-- Active filter chips -->
          <template v-if="hasFilters">
            <span
              v-for="g in filterGroups"
              :key="`fg-${g.id}`"
              class="inline-flex h-7 items-center gap-1 rounded-full bg-blue-100 pl-2.5 pr-1.5 text-[12px] font-medium text-blue-700"
            >
              <Layers :size="11" />
              {{ g.name }}
              <button type="button" class="ml-0.5 rounded-full p-0.5 hover:bg-blue-200" @click="removeFilterGroup(g.id)"><X :size="10" /></button>
            </span>
            <span
              v-for="r in filterRoles"
              :key="`fr-${r.id}`"
              class="inline-flex h-7 items-center gap-1 rounded-full bg-blue-100 pl-2.5 pr-1.5 text-[12px] font-medium text-blue-700"
            >
              <Shield :size="11" />
              {{ r.title ?? r.name }}
              <button type="button" class="ml-0.5 rounded-full p-0.5 hover:bg-blue-200" @click="removeFilterRole(r.id)"><X :size="10" /></button>
            </span>
            <button
              type="button"
              class="text-[12px] text-slate-400 hover:text-slate-600 underline"
              @click="clearFilters"
            >
Сбросить
</button>
          </template>
        </div>

        <!-- Table -->
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
          <div class="grid border-b border-slate-100 px-5 py-3" style="grid-template-columns: 1fr 240px 100px 40px">
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Пользователь</div>
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Роли</div>
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">С</div>
            <div />
          </div>

          <div v-if="loading" class="flex items-center justify-center py-12 text-[13px] text-slate-400">Загрузка…</div>

          <EmptyState v-else-if="!users.length" title="Нет пользователей">
            <template #icon><Users :size="18" /></template>
          </EmptyState>

          <div
            v-for="(u, idx) in users"
            :key="u.id"
            class="group relative grid items-center px-5 py-3.5 transition-colors hover:bg-slate-50/70"
            :class="idx !== users.length - 1 ? 'border-b border-slate-100' : ''"
            style="grid-template-columns: 1fr 240px 100px 40px"
          >
            <div class="flex min-w-0 items-center gap-3 pr-4">
              <div
                class="flex h-8 w-8 flex-none items-center justify-center rounded-full text-[11px] font-bold text-white"
                :style="{ background: avatarColor(u.name) }"
              >
{{ initials(u.name) }}
</div>
              <div class="min-w-0">
                <div class="truncate text-[13px] font-semibold text-slate-900">{{ u.name }}</div>
                <div class="truncate text-[11px] text-slate-400">{{ u.email }}</div>
              </div>
            </div>

            <div class="flex flex-wrap gap-1 pr-3">
              <span
                v-for="r in u.roles"
                :key="r.id"
                class="inline-flex h-5 items-center rounded px-1.5 text-[10px] font-semibold bg-slate-100 text-slate-600"
              >{{ r.title ?? r.name }}</span>
              <span v-if="!u.roles.length" class="text-[11px] text-slate-400">—</span>
            </div>

            <div class="text-[12px] text-slate-400">{{ formatDate(u.created_at) }}</div>

            <div v-if="canCreate || canDelete" class="flex justify-end">
              <DropdownMenu>
                <DropdownMenuTrigger as-child>
                  <button class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-slate-400 transition cursor-pointer hover:bg-slate-100 hover:text-slate-700">
                    <MoreHorizontal :size="15" />
                  </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-44">
                  <DropdownMenuItem v-if="canCreate" @click="openEdit(u)">
                    <Pencil class="mr-2 h-4 w-4 text-slate-400" /> Редактировать
                  </DropdownMenuItem>
                  <DropdownMenuItem @click="openTokens(u)">
                    <KeyRound class="mr-2 h-4 w-4 text-slate-400" /> API-токены
                  </DropdownMenuItem>
                  <DropdownMenuSeparator v-if="canDelete" />
                  <DropdownMenuItem v-if="canDelete" class="text-red-600 focus:text-red-600" @click="openDeleteConfirm(u)">
                    <Trash2 class="mr-2 h-4 w-4" /> Удалить
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
            <div v-else />
          </div>

          <ListPagination v-model:current-page="page" :total-pages="meta.last_page" :total="meta.total" :per-page="meta.per_page" />
        </div>
</div>
    </div>
  </AppShell>

  <ConfirmDialog
    :open="confirmDelete !== null"
    title="Удалить пользователя?"
    :loading="deleting"
    :error="deleteError"
    @update:open="(v: boolean) => !v && closeDeleteConfirm()"
    @confirm="doDeleteUser"
  >
    Пользователь <span class="font-semibold text-slate-900">{{ confirmDelete?.name }}</span> будет удалён без возможности восстановления.
  </ConfirmDialog>

  <!-- Create / Edit modal -->
  <Teleport to="body">
    <div
      v-if="showModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-sm"
      @click.self="closeModal"
    >
      <div class="flex w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_24px_64px_-12px_rgba(15,23,42,0.2)]" style="max-height: 90vh">
        <div class="flex flex-none items-center justify-between border-b border-slate-100 px-6 py-4">
          <div class="text-[15px] font-bold text-slate-900">{{ editing ? 'Редактировать пользователя' : 'Добавить пользователя' }}</div>
          <button class="text-slate-400 transition hover:text-slate-700" @click="closeModal"><X :size="18" /></button>
        </div>

        <div v-if="modalLoading" class="flex items-center justify-center py-10 text-[13px] text-slate-400">
          Загрузка…
        </div>

        <form v-else class="flex-1 overflow-y-auto" @submit.prevent="save" novalidate>
          <FormBody>
            <FormError :message="formError" />
            <FormSection>
              <FormInput
                v-model="form.name"
                label="Имя"
                placeholder="Иван Иванов"
                required
                autocomplete="name"
                :error="errors.name"
              />
              <FormInput
                v-model="form.fio"
                label="ФИО"
                placeholder="Иванов Иван Иванович"
                autocomplete="off"
                :error="errors.fio"
              />
              <FormInput
                v-model="form.email"
                label="Email"
                type="email"
                placeholder="ivan@company.ru"
                required
                autocomplete="email"
                :error="errors.email"
              />
              <FormRow>
                <FormInput
                  v-model="form.login"
                  label="Логин"
                  placeholder="ivan"
                  autocomplete="username"
                  :error="errors.login"
                />
                <FormInput
                  v-model="form.external_id"
                  label="Внешний ID"
                  placeholder="ext-123"
                  autocomplete="off"
                  :error="errors.external_id"
                />
              </FormRow>
              <FormPassword
                v-model="form.password"
                :label="editing ? 'Пароль (оставьте пустым, чтобы не менять)' : 'Пароль'"
                :required="!editing"
                placeholder="Минимум 8 символов"
                autocomplete="new-password"
                :error="errors.password"
              />
            </FormSection>

            <!-- Roles + Groups side by side -->
          <div class="grid gap-3">
<!-- Roles panel -->
            <div class="overflow-hidden rounded-xl border border-slate-200">
              <div class="flex items-center justify-between border-b border-slate-100 px-3 py-2">
                <span class="text-[12px] font-semibold text-slate-700">Роли</span>
                <span class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium text-slate-500">{{ selectedRoles.length }}</span>
              </div>
              <div class="relative border-b border-slate-100 px-2 py-1.5">
                <div class="flex h-7 items-center gap-1.5 rounded-lg bg-slate-50 px-2 focus-within:bg-white focus-within:ring-2 focus-within:ring-blue-100">
                  <Plus :size="11" class="shrink-0 text-slate-400" />
                  <input
                    v-model="roleSearch"
                    type="text"
                    class="min-w-0 flex-1 border-0 focus:border-0 bg-transparent text-[12px] outline-none placeholder:text-slate-400 focus:outline-none"
                    placeholder="Добавить..."
                    @input="onRoleInput"
                    @blur="closeRoleDropdownSoon"
                  />
                </div>
                <div v-if="roleDropdownOpen" class="absolute left-2 right-2 top-full z-10 mt-1 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
                  <button
                    v-for="r in roleResults"
                    :key="r.id"
                    type="button"
                    class="flex w-full items-center gap-2 px-3 py-2 text-left transition hover:bg-blue-50"
                    @mousedown.prevent="addRole(r)"
                  >
                    <Shield :size="11" class="shrink-0 text-slate-400" />
                    <span class="truncate text-[12px] text-slate-900">{{ r.title ?? r.name }}</span>
                  </button>
                </div>
              </div>
              <div class="overflow-y-auto" style="max-height: 128px">
                <div v-if="!selectedRoles.length" class="flex items-center justify-center py-4">
                  <span class="text-[11px] text-slate-400">Нет ролей</span>
                </div>
                <div v-for="r in selectedRoles" :key="r.id" class="group/r flex items-center gap-2 px-3 py-1.5 transition hover:bg-slate-50">
                  <Shield :size="11" class="shrink-0 text-slate-400" />
                  <span class="min-w-0 flex-1 truncate text-[12px] text-slate-900">{{ r.title ?? r.name }}</span>
                  <button type="button" class="flex-none text-slate-300 opacity-0 transition group-hover/r:opacity-100 hover:text-red-500" @click="removeRole(r.name)">
                    <X :size="12" />
                  </button>
                </div>
              </div>
            </div>

            <!-- Groups panel -->
            <div class="overflow-hidden rounded-xl border border-slate-200">
              <div class="flex items-center justify-between border-b border-slate-100 px-3 py-2">
                <span class="text-[12px] font-semibold text-slate-700">Группы</span>
                <span class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium text-slate-500">{{ selectedGroups.length }}</span>
              </div>
              <div class="relative border-b border-slate-100 px-2 py-1.5">
                <div class="flex h-7 items-center gap-1.5 rounded-lg bg-slate-50 px-2 focus-within:bg-white focus-within:ring-2 focus-within:ring-blue-100">
                  <Plus :size="11" class="shrink-0 text-slate-400" />
                  <input
                    v-model="groupSearch"
                    type="text"
                    class="min-w-0 flex-1 border-0 bg-transparent text-[12px] outline-none placeholder:text-slate-400 focus:outline-none"
                    placeholder="Добавить..."
                    @input="onGroupInput"
                    @blur="closeGroupDropdownSoon"
                  />
                </div>
                <div v-if="groupDropdownOpen" class="absolute left-2 right-2 top-full z-10 mt-1 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
                  <button
                    v-for="g in groupResults"
                    :key="g.id"
                    type="button"
                    class="flex w-full items-center gap-2 px-3 py-2 text-left transition hover:bg-blue-50"
                    @mousedown.prevent="addGroup(g)"
                  >
                    <Layers :size="11" class="shrink-0 text-slate-400" />
                    <span class="truncate text-[12px] text-slate-900">{{ g.name }}</span>
                  </button>
                </div>
              </div>
              <div class="overflow-y-auto" style="max-height: 128px">
                <div v-if="!selectedGroups.length" class="flex items-center justify-center py-4">
                  <span class="text-[11px] text-slate-400">Нет групп</span>
                </div>
                <div v-for="g in selectedGroups" :key="g.id" class="group/g flex items-center gap-2 px-3 py-1.5 transition hover:bg-slate-50">
                  <Layers :size="11" class="shrink-0 text-slate-400" />
                  <span class="min-w-0 flex-1 truncate text-[12px] text-slate-900">{{ g.name }}</span>
                  <button type="button" class="flex-none text-slate-300 opacity-0 transition group-hover/g:opacity-100 hover:text-red-500" @click="removeGroup(g.id)">
                    <X :size="12" />
                  </button>
                </div>
              </div>
            </div>
            </div>
          </FormBody>
        </form>
        <FormActions :submitting="submitting" :disabled="modalLoading" @cancel="closeModal" @submit="save" />
      </div>
    </div>
  </Teleport>

  <!-- Tokens modal -->
  <Teleport to="body">
    <div
      v-if="showTokensModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-sm"
      @click.self="closeTokens"
    >
      <div class="flex w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_24px_64px_-12px_rgba(15,23,42,0.2)]" style="max-height: 90vh">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
          <div>
            <div class="text-[15px] font-bold text-slate-900">API-токены</div>
            <div v-if="tokenUser" class="mt-0.5 text-[12px] text-slate-400">{{ tokenUser.name }}</div>
          </div>
          <button class="text-slate-400 transition hover:text-slate-700" @click="closeTokens"><X :size="18" /></button>
        </div>

        <div class="flex-1 overflow-y-auto px-6 py-5">
<!-- Create new token -->
          <div class="mb-5 space-y-2">
            <label class="block text-[12px] font-semibold text-slate-700">Создать новый токен</label>
            <div class="flex gap-2">
              <input
                v-model="newTokenName"
                type="text"
                class="h-9 flex-1 rounded-lg border border-slate-200 bg-slate-50 px-3 text-[13px] outline-none transition focus:border-blue-400 focus:bg-white focus:ring-2 focus:ring-blue-100"
                placeholder="Название токена"
                @keydown.enter="createToken"
              />
            </div>
            <div class="flex items-center gap-2">
              <div class="relative flex-1">
                <input
                  v-model="newTokenExpiresAt"
                  type="date"
                  class="h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-[13px] text-slate-700 outline-none transition focus:border-blue-400 focus:bg-white focus:ring-2 focus:ring-blue-100 disabled:opacity-40"
                  :disabled="newTokenExpiresAt === 'never'"
                  :placeholder="'Дата окончания (необязательно)'"
                />
              </div>
              <label class="flex cursor-pointer items-center gap-1.5 text-[12px] text-slate-500 select-none">
                <input
                  type="checkbox"
                  class="h-3.5 w-3.5 rounded accent-blue-600"
                  :checked="newTokenExpiresAt === 'never'"
                  @change="newTokenExpiresAt = (newTokenExpiresAt === 'never') ? '' : 'never'"
                />
                Бессрочный
              </label>
              <button
                class="h-9 rounded-xl bg-blue-600 px-4 text-[13px] font-medium text-white transition hover:bg-blue-700 disabled:opacity-50"
                :disabled="tokenCreating || !newTokenName.trim()"
                @click="createToken"
              >
                {{ tokenCreating ? '…' : 'Создать' }}
              </button>
            </div>
            <div v-if="tokenCreateError" class="rounded-lg bg-red-50 px-3 py-2 text-[12px] text-red-700">{{ tokenCreateError }}</div>
          </div>

          <!-- Newly created token banner -->
          <div v-if="createdToken" class="mb-5 overflow-hidden rounded-xl border border-emerald-200 bg-emerald-50">
            <div class="flex items-center justify-between border-b border-emerald-100 px-4 py-2.5">
              <div>
                <span class="text-[12px] font-semibold text-emerald-800">Токен создан — скопируйте сейчас, он больше не будет показан</span>
                <div class="mt-0.5 text-[11px] text-emerald-600">Создан {{ formatDate(createdToken.created_at) }}</div>
              </div>
              <button class="text-emerald-500 hover:text-emerald-700" @click="dismissCreatedToken"><X :size="14" /></button>
            </div>
            <div class="flex items-center gap-2 px-4 py-3">
              <code class="min-w-0 flex-1 break-all text-[12px] font-mono text-emerald-900">{{ createdToken.plain_text_token }}</code>
              <button
                class="flex h-7 w-7 flex-none items-center justify-center rounded-lg text-emerald-600 transition hover:bg-emerald-100"
                @click="copyToken(createdToken.plain_text_token, createdToken.id)"
              >
                <Check v-if="copiedTokenId === createdToken.id" :size="14" />
                <Copy v-else :size="14" />
              </button>
            </div>
          </div>

          <!-- Tokens list -->
          <div v-if="tokensError" class="mb-4 rounded-lg bg-red-50 px-4 py-2.5 text-[13px] text-red-700">{{ tokensError }}</div>

          <div v-if="tokensLoading" class="flex items-center justify-center py-8 text-[13px] text-slate-400">Загрузка…</div>

          <div v-else-if="!tokens.length" class="flex flex-col items-center justify-center gap-2 py-8 text-center">
            <KeyRound :size="28" class="text-slate-200" />
            <p class="text-[13px] text-slate-400">Нет токенов</p>
          </div>

          <div v-else class="overflow-hidden rounded-xl border border-slate-200">
            <div
              v-for="(t, idx) in tokens"
              :key="t.id"
              class="flex items-center gap-3 px-4 py-3 transition hover:bg-slate-50"
              :class="idx !== tokens.length - 1 ? 'border-b border-slate-100' : ''"
            >
              <KeyRound :size="14" class="flex-none text-slate-300" />
              <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                  <span class="text-[13px] font-medium text-slate-800">{{ t.name }}</span>
                  <span
                    v-if="t.expires_at"
                    class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold"
                    :class="new Date(t.expires_at) < new Date() ? 'bg-red-100 text-red-600' : 'bg-amber-100 text-amber-700'"
                  >
                    до {{ formatDate(t.expires_at) }}
                  </span>
                  <span v-else class="inline-flex items-center rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-500">бессрочный</span>
                </div>
                <div class="mt-0.5 text-[11px] text-slate-400">
                  Создан {{ formatDate(t.created_at) }}
                  <template v-if="t.last_used_at"> · Использован {{ formatDate(t.last_used_at) }}</template>
                </div>
              </div>
              <button
                class="flex h-7 w-7 flex-none items-center justify-center rounded-lg text-slate-300 transition hover:bg-red-50 hover:text-red-500 disabled:opacity-40"
                :disabled="revokingId === t.id"
                @click="revokeToken(t.id)"
              >
                <Trash2 :size="13" />
              </button>
            </div>
          </div>
</div>

        <div class="flex justify-end border-t border-slate-100 px-6 py-4">
          <button class="h-9 rounded-xl border border-slate-200 px-4 text-[13px] font-medium text-slate-600 transition hover:bg-slate-50" @click="closeTokens">Закрыть</button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
