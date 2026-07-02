<script setup lang="ts">
import AppShell from '@/layouts/AppShell.vue'
import EmptyState from '@/components/EmptyState.vue'
import PageHeader from '@/components/PageHeader.vue'
import SearchInput from '@/components/SearchInput.vue'
import {
  FormActions, FormBody, FormError, FormInput, FormSelect,
} from '@/components/form'
import {useDashboardNavigation} from '@/composables/useDashboardNavigation'
import {useConnectionList} from '@/modules/proxy/composables/useConnectionList'
import {useConnectionModal} from '@/modules/proxy/composables/useConnectionModal'
import {Head, router} from '@inertiajs/vue3'
import {
  DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {ArrowLeft, KeyRound, Loader2, MoreHorizontal, Pencil, Plus, Trash2, X} from 'lucide-vue-next'

const {navigationItems} = useDashboardNavigation()
const {loading, search, filtered, load} = useConnectionList()
const {
  open, isEditing, saving, formError, errors,
  types, name, credentialType, values, secretFilled, currentFields,
  selectType, openCreate, openEdit, close, save, remove,
} = useConnectionModal(load)

function secretHint(key: string): string {
  return secretFilled.value[key] ? 'Сохранён — оставьте пустым, чтобы не менять' : ''
}
</script>

<template>
  <Head title="Доступы" />

  <AppShell title="Доступы" :navigation-items="navigationItems">
    <div class="min-h-full bg-slate-50">
      <div class="mx-auto max-w-5xl px-6 py-8">
        <button
            class="mb-3 inline-flex items-center gap-1.5 text-[13px] text-slate-500 transition hover:text-slate-900"
            @click="router.visit('/proxy/endpoints')"
        >
          <ArrowLeft :size="14" />
          Интеграции
        </button>

        <PageHeader title="Доступы"
                    subtitle="Переиспользуемые доступы к внешним сервисам. Тип определяет набор полей; секреты шифруются.">
          <template #actions>
            <button
                class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3.5 py-2 text-[13px] font-semibold text-white transition hover:bg-blue-700"
                @click="openCreate"
            >
              <Plus :size="15" />
              Создать
            </button>
          </template>
        </PageHeader>

        <div class="mb-4">
          <SearchInput v-model="search" placeholder="Поиск по доступам..." />
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
          <div v-if="loading" class="flex items-center justify-center py-16 text-slate-400">
            <Loader2 :size="20" class="animate-spin" />
          </div>

          <template v-else>
            <div class="grid border-b border-slate-100 px-5 py-3" style="grid-template-columns: 1fr 200px 40px">
              <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Доступ</div>
              <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Тип</div>
              <div />
            </div>

            <EmptyState v-if="!filtered.length" title="Доступов пока нет" subtitle="Создайте первый доступ">
              <template #icon>
                <KeyRound :size="20" />
              </template>
            </EmptyState>

            <div
                v-for="(conn, idx) in filtered"
                :key="conn.id"
                class="group grid items-center px-5 py-4 transition-colors hover:bg-slate-50/70"
                :class="idx !== filtered.length - 1 ? 'border-b border-slate-100' : ''"
                style="grid-template-columns: 1fr 200px 40px"
            >
              <div class="flex min-w-0 cursor-pointer items-center gap-2 pr-4" @click="openEdit(conn)">
                <div class="flex h-7 w-7 flex-none items-center justify-center rounded-lg bg-violet-50 text-violet-600">
                  <KeyRound :size="14" />
                </div>
                <span class="truncate text-[13px] font-semibold text-slate-900">{{ conn.name }}</span>
              </div>
              <div>
                <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600">
                  {{ conn.credential_label }}
                </span>
              </div>
              <div class="flex justify-end">
                <DropdownMenu>
                  <DropdownMenuTrigger as-child>
                    <button class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" @click.stop>
                      <MoreHorizontal :size="15" />
                    </button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="end" class="w-44">
                    <DropdownMenuItem @click.stop="openEdit(conn)">
                      <Pencil class="mr-2 h-4 w-4 text-slate-400" />
                      Редактировать
                    </DropdownMenuItem>
                    <DropdownMenuItem @click.stop="remove(conn)">
                      <Trash2 class="mr-2 h-4 w-4 text-rose-400" />
                      Удалить
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </div>
            </div>
          </template>
        </div>
      </div>
    </div>
  </AppShell>

  <Teleport to="body">
    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-sm"
        @click.self="close"
    >
      <div class="flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_24px_64px_-12px_rgba(15,23,42,0.2)]">
        <div class="flex shrink-0 items-start justify-between border-b border-slate-100 px-6 py-4">
          <div class="text-[15px] font-bold text-slate-900">{{ isEditing ? 'Редактировать доступ' : 'Новый доступ' }}</div>
          <button class="ml-4 shrink-0 text-slate-400 transition hover:text-slate-700" @click="close">
            <X :size="18" />
          </button>
        </div>

        <form class="flex-1 overflow-y-auto" novalidate @submit.prevent="save">
          <FormBody>
            <FormError :message="formError" />
            <FormInput v-model="name" label="Название" required :error="errors.name" />
            <FormSelect
                :model-value="credentialType"
                label="Тип доступа"
                :error="errors.credential_type"
                @update:model-value="(v: string) => selectType(v)"
            >
              <option v-for="t in types" :key="t.type" :value="t.type">{{ t.group }}: {{ t.label }}</option>
            </FormSelect>

            <FormInput
                v-for="field in currentFields"
                :key="field.key"
                v-model="values[field.key] as string"
                :label="field.label"
                :type="field.secret ? 'password' : 'text'"
                :placeholder="field.secret ? secretHint(field.key) : (field.example ?? '')"
                :error="errors[`values.${field.key}`]"
            />
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
</template>
