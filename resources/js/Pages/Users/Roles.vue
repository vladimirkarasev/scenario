<script setup lang="ts">
import AppShell from '@/layouts/AppShell.vue'
import EmptyState from '@/components/EmptyState.vue'
import PageHeader from '@/components/PageHeader.vue'
import SearchInput from '@/components/SearchInput.vue'
import CopyButton from '@/components/CopyButton.vue'
import UsersTabs from '@/modules/users/components/UsersTabs.vue'
import {
  DropdownMenu, DropdownMenuContent, DropdownMenuItem,
  DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  FormActions, FormBody, FormError, FormInput, FormPermissionGroups, FormSection, FormTextarea,
} from '@/components/form'
import {useDashboardNavigation} from '@/composables/useDashboardNavigation'
import {useAuthStore} from '@/stores/auth'
import {useRoleList} from '@/modules/roles/composables/useRoleList'
import {useRoleModal} from '@/modules/roles/composables/useRoleModal'
import type {Role} from '@/modules/roles/types/role'
import {Head, Link} from '@inertiajs/vue3'
import {Layers, Lock, MoreHorizontal, Pencil, Plus, Shield, Trash2, Users, X} from 'lucide-vue-next'
import {computed} from 'vue'

const {navigationItems} = useDashboardNavigation()
const auth = useAuthStore()
const canManage = computed(() => auth.hasPermission('role_create'))
const canDelete = computed(() => auth.hasPermission('role_delete'))

const {search, loading, roles, availablePermissions, filteredRoles, permissionGroups, load} = useRoleList()

const {
  showModal, editing, form, errors, formError, submitting,
  openCreate, openEdit, close: closeModal, save,
  confirmDelete, deleting, deleteError,
  openDeleteConfirm, closeDeleteConfirm, doDelete: doDeleteRole,
} = useRoleModal(availablePermissions, load)

const permissionGroupsForFormPermissions = computed(() =>
    Object.entries(permissionGroups.value).map(([title, perms]) => ({
      title,
      perms: perms.map(p => ({name: p.name, title: p.label})),
    })),
)

function displayTitle(r: Role): string {
  return r.title ?? r.name.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
}
</script>

<template>
  <Head title="Роли"/>

  <AppShell title="Роли" :navigation-items="navigationItems">
    <div class="app-page">
      <div class="app-page-container max-w-6xl">
        <PageHeader title="Пользователи и роли" subtitle="Управление пользователями, группами и ролями доступа.">
          <template #actions>
            <button
                v-if="canManage"
                class="inline-flex h-9 shrink-0 items-center gap-2 rounded-xl bg-blue-600 px-4 text-[13px] font-medium text-white shadow-sm transition hover:bg-blue-700"
                @click="openCreate"
            >
              <Plus :size="15"/>
              Новая роль
            </button>
          </template>
        </PageHeader>

        <!-- Stats -->
        <div class="mb-6 grid grid-cols-3 gap-4">
          <Link href="/users"
                class="rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-[0_1px_2px_rgba(15,23,42,0.04)] transition hover:border-blue-200">
            <div
                class="mb-1 flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
              <Users :size="11"/>
              Пользователи
            </div>
            <div class="text-3xl font-bold tabular-nums text-slate-400">—</div>
            <div class="mt-0.5 text-[11px] text-slate-400">в системе</div>
          </Link>
          <Link href="/users/groups"
                class="rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-[0_1px_2px_rgba(15,23,42,0.04)] transition hover:border-blue-200">
            <div
                class="mb-1 flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
              <Layers :size="11"/>
              Группы
            </div>
            <div class="text-3xl font-bold tabular-nums text-slate-400">—</div>
            <div class="mt-0.5 text-[11px] text-slate-400">в системе</div>
          </Link>
          <div
              class="rounded-xl border border-blue-300 bg-white px-5 py-4 shadow-[0_1px_2px_rgba(15,23,42,0.04)] ring-1 ring-blue-200">
            <div
                class="mb-1 flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
              <Shield :size="11"/>
              Роли
            </div>
            <div class="text-3xl font-bold tabular-nums text-slate-900">{{ roles.length }}</div>
            <div class="mt-0.5 text-[11px] text-slate-400">системных</div>
          </div>
        </div>

        <!-- Tabs + search row -->
        <div class="mb-4 flex items-center justify-between gap-4">
          <UsersTabs active="roles"/>
          <div class="w-56">
            <SearchInput v-model="search" placeholder="Поиск ролей..."/>
          </div>
        </div>

        <!-- Table -->
        <div class="app-panel">
          <div class="grid border-b border-slate-100 px-5 py-3" style="grid-template-columns: 1fr 1fr 80px 40px">
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Роль</div>
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Описание</div>
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Пользователей</div>
            <div/>
          </div>

          <div v-if="loading" class="flex items-center justify-center py-12 text-[13px] text-slate-400">Загрузка…</div>

          <EmptyState v-else-if="!filteredRoles.length" title="Нет ролей">
            <template #icon>
              <Shield :size="18"/>
            </template>
          </EmptyState>

          <div
              v-for="(r, idx) in filteredRoles"
              :key="r.id"
              class="group relative grid items-center px-5 py-4 transition-colors hover:bg-slate-50/70"
              :class="idx !== filteredRoles.length - 1 ? 'border-b border-slate-100' : ''"
              style="grid-template-columns: 1fr 1fr 80px 40px"
          >
            <div class="flex items-center gap-3 pr-4">
              <div class="flex h-8 w-8 flex-none items-center justify-center rounded-lg bg-slate-100 text-slate-500">
                <Shield :size="14"/>
              </div>
              <div class="min-w-0">
                <div class="flex items-center gap-1.5">
                  <span class="text-[13px] font-semibold text-slate-900">{{ displayTitle(r) }}</span>
                  <Lock v-if="r.is_system" :size="12" class="shrink-0 text-amber-500"/>
                </div>
                <CopyButton
                    :text="r.name"
                    :label="r.name"
                    :copied-label="r.name"
                    class="mt-0.5 flex items-center gap-1 font-mono text-[11px] text-slate-400 transition hover:text-slate-700"
                    title="Скопировать название роли"
                />
              </div>
            </div>

            <div class="pr-4 text-[13px] text-slate-500">
              <span v-if="r.description">{{ r.description }}</span>
              <span v-else class="text-slate-300">—</span>
            </div>

            <div class="text-[13px] font-semibold tabular-nums text-slate-900">{{ r.users_count }}</div>

            <div v-if="canManage || (canDelete && !r.is_system)" class="flex justify-end">
              <DropdownMenu>
                <DropdownMenuTrigger as-child>
                  <button
                      class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-slate-400 opacity-0 transition group-hover:opacity-100 hover:bg-slate-100 hover:text-slate-700">
                    <MoreHorizontal :size="15"/>
                  </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-44">
                  <DropdownMenuItem v-if="canManage" @click="openEdit(r)">
                    <Pencil class="mr-2 h-4 w-4 text-slate-400"/>
                    Редактировать
                  </DropdownMenuItem>
                  <template v-if="canDelete && !r.is_system">
                    <DropdownMenuSeparator/>
                    <DropdownMenuItem class="text-red-600 focus:text-red-600" @click="openDeleteConfirm(r)">
                      <Trash2 class="mr-2 h-4 w-4"/>
                      Удалить
                    </DropdownMenuItem>
                  </template>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
            <div v-else/>
          </div>
        </div>
      </div>
    </div>
  </AppShell>

  <!-- Confirm delete -->
  <Teleport to="body">
    <div
        v-if="confirmDelete !== null"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-sm"
        @click.self="closeDeleteConfirm"
    >
      <div
          class="w-full max-w-sm overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_24px_64px_-12px_rgba(15,23,42,0.2)]">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
          <div class="text-[15px] font-bold text-slate-900">Удалить роль?</div>
          <button class="text-slate-400 transition hover:text-slate-700" @click="closeDeleteConfirm">
            <X :size="18"/>
          </button>
        </div>
        <div class="px-6 py-5 text-[13px] text-slate-600">
          Роль <span class="font-semibold text-slate-900">{{ displayTitle(confirmDelete) }}</span> будет удалена без
          возможности восстановления.
        </div>
        <div v-if="deleteError" class="mx-6 mb-4 rounded-lg bg-red-50 px-4 py-2.5 text-[13px] text-red-700">
          {{ deleteError }}
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4">
          <button
              class="h-9 rounded-xl border border-slate-200 px-4 text-[13px] font-medium text-slate-600 transition hover:bg-slate-50"
              @click="closeDeleteConfirm">Отмена
          </button>
          <button
              class="h-9 rounded-xl bg-red-600 px-4 text-[13px] font-medium text-white transition hover:bg-red-700 disabled:opacity-50"
              :disabled="deleting" @click="doDeleteRole">{{ deleting ? 'Удаление…' : 'Удалить' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>

  <!-- Modal -->
  <Teleport to="body">
    <div
        v-if="showModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-sm"
        @click.self="closeModal"
    >
      <div
          class="flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_24px_64px_-12px_rgba(15,23,42,0.2)]">
        <div class="flex shrink-0 items-center justify-between border-b border-slate-100 px-6 py-4">
          <div class="text-[15px] font-bold text-slate-900">{{ editing ? 'Редактировать роль' : 'Новая роль' }}</div>
          <button class="text-slate-400 transition hover:text-slate-700" @click="closeModal">
            <X :size="18"/>
          </button>
        </div>

        <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="save" novalidate>
          <FormBody>
            <FormError :message="formError"/>
            <FormSection>
              <FormInput
                  v-model="form.title"
                  label="Название"
                  placeholder="Менеджер HR"
                  :error="errors.title"
              />
              <FormInput
                  v-model="form.name"
                  label="Системное имя"
                  placeholder="hr_manager"
                  required
                  :error="errors.name"
              />
              <FormTextarea
                  v-model="form.description"
                  label="Описание"
                  placeholder="Роль для сотрудников HR-отдела…"
                  :rows="2"
                  :error="errors.description"
              />
            </FormSection>
            <FormPermissionGroups
                v-if="availablePermissions.length > 0"
                v-model="form.permissions"
                :groups="permissionGroupsForFormPermissions"
                label="Разрешения"
                :hint="`Выбрано ${form.permissions.length} из ${availablePermissions.length}`"
            />
          </FormBody>
          <FormActions class="shrink-0" :submitting="submitting" @cancel="closeModal" @submit="save"/>
        </form>
      </div>
    </div>
  </Teleport>
</template>
