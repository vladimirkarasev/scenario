<script setup lang="ts">
import AppShell from '@/layouts/AppShell.vue'
import EmptyState from '@/components/EmptyState.vue'
import ListPagination from '@/components/ListPagination.vue'
import PageHeader from '@/components/PageHeader.vue'
import SearchInput from '@/components/SearchInput.vue'
import {
  DropdownMenu, DropdownMenuContent, DropdownMenuItem,
  DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  FormActions, FormAutoSlug, FormBody, FormCheckbox, FormError, FormInput, FormSection, FormTextarea,
} from '@/components/form'
import {useDashboardNavigation} from '@/composables/useDashboardNavigation'
import {useAuthStore} from '@/stores/auth'
import {useGroupList} from '@/modules/groups/composables/useGroupList'
import {useGroupModal} from '@/modules/groups/composables/useGroupModal'
import UsersTabs from '@/modules/users/components/UsersTabs.vue'
import {Head, Link} from '@inertiajs/vue3'
import {
  Check,
  Copy,
  Layers,
  MoreHorizontal,
  Pencil,
  Plus,
  Shield,
  Trash2,
  UserCheck,
  UserMinus,
  Users,
  X
} from 'lucide-vue-next'
import {computed, ref} from 'vue'

const {navigationItems} = useDashboardNavigation()
const auth = useAuthStore()
const canManage = computed(() => auth.hasPermission('group_create'))
const canDelete = computed(() => auth.hasPermission('group_delete'))

const copiedId = ref<string | null>(null)

const {search, page, loading, groups, meta, load} = useGroupList()

const {
  showModal, editing, form, errors, formError, submitting,
  members, loadingMembers, memberSearch, memberResults, memberSearchOpen,
  onMemberSearchInput, addMember, removeMember,
  openCreate, openEdit, close: closeModal, save,
  toggleActive: toggleGroup,
  confirmDelete, deleting, deleteError,
  openDeleteConfirm, closeDeleteConfirm, doDelete: doDeleteGroup,
} = useGroupModal(load)

function copyText(text: string, key: string): void {
  navigator.clipboard.writeText(text).catch(() => {
  })
  copiedId.value = key
  setTimeout(() => {
    copiedId.value = null
  }, 1500)
}

function closeMemberSearchSoon(): void {
  setTimeout(() => {
    memberSearchOpen.value = false
  }, 150)
}
</script>

<template>
  <Head title="Группы"/>

  <AppShell title="Группы" :navigation-items="navigationItems">
    <div class="min-h-full bg-slate-50">
      <div class="mx-auto max-w-6xl px-6 py-8">
        <!-- Header -->
        <PageHeader title="Пользователи и роли" subtitle="Управление пользователями, группами и ролями доступа.">
          <template #actions>
            <button
                v-if="canManage"
                class="inline-flex h-9 shrink-0 items-center gap-2 rounded-xl bg-blue-600 px-4 text-[13px] font-medium text-white shadow-sm transition hover:bg-blue-700"
                @click="openCreate"
            >
              <Plus :size="15"/>
              Новая группа
            </button>
          </template>
        </PageHeader>

        <!-- Stats -->
        <div class="mb-6 grid grid-cols-3 gap-4">
          <Link
              href="/users"
              class="rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-[0_1px_2px_rgba(15,23,42,0.04)] transition hover:border-blue-200"
          >
            <div
                class="mb-1 flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
              <Users :size="11"/>
              Пользователи
            </div>
            <div class="text-3xl font-bold tabular-nums text-slate-400">—</div>
            <div class="mt-0.5 text-[11px] text-slate-400">в системе</div>
          </Link>
          <div
              class="rounded-xl border border-blue-300 bg-white px-5 py-4 shadow-[0_1px_2px_rgba(15,23,42,0.04)] ring-1 ring-blue-200">
            <div
                class="mb-1 flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
              <Layers :size="11"/>
              Группы
            </div>
            <div class="text-3xl font-bold tabular-nums text-slate-900">{{ meta.total }}</div>
            <div class="mt-0.5 text-[11px] text-slate-400">в системе</div>
          </div>
          <Link
              href="/users/roles"
              class="rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-[0_1px_2px_rgba(15,23,42,0.04)] transition hover:border-blue-200"
          >
            <div
                class="mb-1 flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
              <Shield :size="11"/>
              Роли
            </div>
            <div class="text-3xl font-bold tabular-nums text-slate-400">—</div>
            <div class="mt-0.5 text-[11px] text-slate-400">системных</div>
          </Link>
        </div>

        <!-- Tabs + search row -->
        <div class="mb-4 flex items-center justify-between gap-4">
          <UsersTabs active="groups"/>
          <div class="w-56">
            <SearchInput v-model="search" placeholder="Поиск групп..."/>
          </div>
        </div>

        <!-- Groups table -->
        <div
            class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
          <div class="grid border-b border-slate-100 px-5 py-3" style="grid-template-columns: 1fr 80px 100px 40px">
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Группа</div>
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Участники</div>
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Статус</div>
            <div/>
          </div>

          <div v-if="loading" class="flex items-center justify-center py-12 text-[13px] text-slate-400">Загрузка…</div>

          <EmptyState v-else-if="!groups.length" title="Нет групп" subtitle="Создайте первую группу">
            <template #icon>
              <Layers :size="18"/>
            </template>
          </EmptyState>

          <div
              v-for="(g, idx) in groups"
              :key="g.id"
              class="group relative grid items-center px-5 py-3.5 transition-colors hover:bg-slate-50/70"
              :class="idx !== groups.length - 1 ? 'border-b border-slate-100' : ''"
              style="grid-template-columns: 1fr 80px 100px 40px"
          >
            <div class="min-w-0 pr-4">
              <div class="text-[13px] font-semibold text-slate-900">{{ g.name }}</div>
              <button
                  class="mt-0.5 flex items-center gap-1 font-mono text-[11px] text-slate-400 transition hover:text-slate-700"
                  @click="copyText(g.slug, `slug-${g.id}`)"
              >
                <component :is="copiedId === `slug-${g.id}` ? Check : Copy" :size="10"/>
                {{ g.slug }}
              </button>
              <div v-if="g.description" class="mt-0.5 truncate text-[11px] text-slate-400">{{ g.description }}</div>
            </div>

            <div class="text-[13px] font-semibold tabular-nums text-slate-900">{{ g.members_count }}</div>

            <div>
              <span
                  class="inline-flex h-5 items-center rounded-full px-2 text-[11px] font-semibold"
                  :class="g.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-400'"
              >{{ g.is_active ? 'Активна' : 'Отключена' }}</span>
            </div>

            <div v-if="canManage || canDelete" class="flex justify-end">
              <DropdownMenu>
                <DropdownMenuTrigger as-child>
                  <button
                      class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-slate-400 opacity-0 transition group-hover:opacity-100 hover:bg-slate-100 hover:text-slate-700">
                    <MoreHorizontal :size="15"/>
                  </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-44">
                  <DropdownMenuItem v-if="canManage" @click="openEdit(g)">
                    <Pencil class="mr-2 h-4 w-4 text-slate-400"/>
                    Редактировать
                  </DropdownMenuItem>
                  <DropdownMenuItem v-if="canManage" @click="toggleGroup(g)">
                    <component :is="g.is_active ? UserMinus : UserCheck" class="mr-2 h-4 w-4 text-slate-400"/>
                    {{ g.is_active ? 'Отключить' : 'Активировать' }}
                  </DropdownMenuItem>
                  <DropdownMenuSeparator v-if="canDelete"/>
                  <DropdownMenuItem v-if="canDelete" class="text-red-600 focus:text-red-600" @click="openDeleteConfirm(g)">
                    <Trash2 class="mr-2 h-4 w-4"/>
                    Удалить
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
            <div v-else/>
          </div>

          <ListPagination v-model:current-page="page" :total-pages="meta.last_page" :total="meta.total"
                          :per-page="meta.per_page"/>
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
          <div class="text-[15px] font-bold text-slate-900">Удалить группу?</div>
          <button class="text-slate-400 transition hover:text-slate-700" @click="closeDeleteConfirm">
            <X :size="18"/>
          </button>
        </div>
        <div class="px-6 py-5 text-[13px] text-slate-600">
          Группа <span class="font-semibold text-slate-900">{{ confirmDelete.name }}</span> будет удалена без
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
              :disabled="deleting" @click="doDeleteGroup">{{ deleting ? 'Удаление…' : 'Удалить' }}
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
        @click.self="showModal = false; editing = null"
    >
      <div
          class="w-full overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_24px_64px_-12px_rgba(15,23,42,0.2)]"
          :class="editing ? 'max-w-2xl' : 'max-w-md'"
      >
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
          <div class="text-[15px] font-bold text-slate-900">{{
              editing ? 'Редактировать группу' : 'Новая группа'
            }}
          </div>
          <button class="text-slate-400 transition hover:text-slate-700" @click="showModal = false; editing = null">
            <X :size="18"/>
          </button>
        </div>

        <form @submit.prevent="save" novalidate>
          <div class="flex gap-0 divide-x divide-slate-100">
            <!-- Left: group fields -->
            <div class="min-w-0 flex-1">
              <FormBody>
                <FormError :message="formError"/>
                <FormSection>
                  <FormInput
                      name="name"
                      v-model="form.name"
                      label="Название"
                      placeholder="HR-команда"
                      required
                      :error="errors.name"
                  />
                  <FormAutoSlug
                      name="slug"
                      v-model="form.slug"
                      :source="form.name"
                      label="Slug"
                      placeholder="hr-team"
                      required
                      :auto-lock-on-edit="!!editing"
                      :error="errors.slug"
                  />
                  <FormTextarea
                      name="description"
                      v-model="form.description"
                      label="Описание"
                      placeholder="Сотрудники отдела..."
                      :error="errors.description"
                  />
                  <FormCheckbox v-model="form.is_active" label="Активна"/>
                </FormSection>
              </FormBody>
            </div>

            <!-- Right: members (edit only) -->
            <div v-if="editing" class="flex w-72 flex-none flex-col">
              <div class="border-b border-slate-100 px-4 py-3">
                <div class="text-[12px] font-semibold text-slate-700">
                  Участники
                  <span class="ml-1 rounded-full bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium text-slate-500">{{
                      members.length
                    }}</span>
                </div>
              </div>

              <!-- Member search -->
              <div class="relative border-b border-slate-100 px-3 py-2.5">
                <div
                    class="flex h-8 items-center gap-2 rounded-lg bg-slate-50 px-2.5 focus-within:bg-white focus-within:ring-2 focus-within:ring-blue-100">
                  <Plus :size="12" class="shrink-0 text-slate-400"/>
                  <input
                      v-model="memberSearch"
                      type="text"
                      class="min-w-0 flex-1 bg-transparent text-[12px] outline-none focus:outline-none placeholder:text-slate-400 border-0"
                      placeholder="Добавить пользователя..."
                      @input="onMemberSearchInput"
                      @blur="closeMemberSearchSoon"
                  />
                </div>
                <!-- Search dropdown -->
                <div
                    v-if="memberSearchOpen"
                    class="absolute left-3 right-3 top-full z-10 mt-1 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg"
                >
                  <button
                      v-for="u in memberResults"
                      :key="u.id"
                      type="button"
                      class="flex w-full items-center gap-2.5 px-3 py-2 text-left transition hover:bg-blue-50"
                      @mousedown.prevent="addMember(u)"
                  >
                    <div
                        class="flex h-6 w-6 flex-none items-center justify-center rounded-full bg-slate-200 text-[10px] font-bold text-slate-600">
                      {{ u.name.charAt(0).toUpperCase() }}
                    </div>
                    <div class="min-w-0">
                      <div class="truncate text-[12px] font-medium text-slate-900">{{ u.name }}</div>
                      <div class="truncate text-[11px] text-slate-400">{{ u.email }}</div>
                    </div>
                  </button>
                </div>
              </div>

              <!-- Members list -->
              <div class="flex-1 overflow-y-auto" style="max-height: 260px">
                <div v-if="loadingMembers" class="flex items-center justify-center py-8 text-[12px] text-slate-400">
                  Загрузка…
                </div>
                <div v-else-if="!members.length" class="flex flex-col items-center justify-center py-8 text-center">
                  <Users :size="20" class="mb-2 text-slate-300"/>
                  <div class="text-[12px] text-slate-400">Нет участников</div>
                </div>
                <div
                    v-for="m in members"
                    :key="m.id"
                    class="group/m flex items-center gap-2.5 px-4 py-2.5 transition hover:bg-slate-50"
                >
                  <div
                      class="flex h-7 w-7 flex-none items-center justify-center rounded-full bg-slate-100 text-[11px] font-bold text-slate-500">
                    {{ m.name.charAt(0).toUpperCase() }}
                  </div>
                  <div class="min-w-0 flex-1">
                    <div class="truncate text-[12px] font-medium text-slate-900">{{ m.name }}</div>
                    <div class="truncate text-[11px] text-slate-400">{{ m.login ?? m.email }}</div>
                  </div>
                  <button
                      type="button"
                      class="flex-none text-slate-300 opacity-0 transition group-hover/m:opacity-100 hover:text-red-500"
                      @click="removeMember(m)"
                  >
                    <X :size="13"/>
                  </button>
                </div>
              </div>
            </div>
          </div>
          <FormActions :submitting="submitting" @cancel="closeModal" @submit="save"/>
        </form>
      </div>
    </div>
  </Teleport>
</template>
