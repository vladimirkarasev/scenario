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
import {useDashboardNavigation} from '@/composables/useDashboardNavigation'
import {useActionList} from '@/modules/actions/composables/useActionList'
import {useActionModal} from '@/modules/actions/composables/useActionModal'
import {useActionRunModal} from '@/modules/actions/composables/useActionRunModal'
import {useActionScheduleModal} from '@/modules/actions/composables/useActionScheduleModal'
import type {Action} from '@/modules/actions/types/action'
import {Head} from '@inertiajs/vue3'
import {
  CalendarClock, Clock3, MoreHorizontal, Pencil, Play, Plus,
  RefreshCw, Shield, ShieldOff, Trash2, Zap,
} from 'lucide-vue-next'
import {
  DropdownMenu, DropdownMenuContent, DropdownMenuItem,
  DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'

const {navigationItems} = useDashboardNavigation()

const list = useActionList()

const modal = useActionModal(
    () => list.actionTypes.value,
    (action) => {
      list.upsert(action)
    },
)

const runModal = useActionRunModal(() => {
  list.load()
})

const scheduleModal = useActionScheduleModal((id, schedule) => {
  const found = list.actions.value.find(a => a.id === id)
  if (found) list.upsert({...found, schedule})
})

async function toggleActive(item: Action): Promise<void> {
  const result = await modal.toggleActive(item)
  if (result) list.upsert(result)
}

async function deleteAction(item: Action): Promise<void> {
  if (await modal.remove(item)) list.remove(item.id)
}

function formatDate(value: string | null | undefined): string {
  if (!value) return '—'
  return new Intl.DateTimeFormat('ru-RU', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value))
}
</script>

<template>
  <Head title="Действия"/>

  <AppShell title="Действия" :navigation-items="navigationItems">
    <div class="min-h-full bg-slate-50">
      <div class="mx-auto max-w-6xl px-6 py-8">
        <PageHeader title="Действия" subtitle="Управление backend actions, ручными запусками, очередями и расписанием.">
          <template #actions>
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
                @click="modal.openCreate">
              <Plus :size="15"/>
              Новый action
            </button>
          </template>
        </PageHeader>

        <div v-if="list.error.value"
             class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-[13px] text-red-700">
          {{ list.error.value }}
        </div>

        <div class="mb-6 grid gap-4 md:grid-cols-3">
          <div class="rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
            <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Всего</div>
            <div class="mt-1.5 text-3xl font-bold tabular-nums text-slate-900">{{ list.actions.value.length }}</div>
          </div>
          <div
              class="rounded-xl border border-emerald-100 bg-emerald-50 px-5 py-4 shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
            <div class="text-[11px] font-semibold uppercase tracking-wider text-emerald-600">Активных</div>
            <div class="mt-1.5 text-3xl font-bold tabular-nums text-emerald-700">{{ list.activeCount.value }}</div>
          </div>
          <div class="rounded-xl border border-blue-100 bg-blue-50 px-5 py-4 shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
            <div class="text-[11px] font-semibold uppercase tracking-wider text-blue-600">По расписанию</div>
            <div class="mt-1.5 text-3xl font-bold tabular-nums text-blue-700">{{ list.scheduledCount.value }}</div>
          </div>
        </div>

        <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div class="max-w-sm flex-1">
            <SearchInput v-model="list.search.value" placeholder="Поиск по actions..."/>
          </div>
          <button
              class="inline-flex h-9 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-[13px] font-medium text-slate-600 transition hover:bg-slate-50"
              :disabled="list.loading.value" @click="list.load">
            <RefreshCw :size="14" :class="list.loading.value ? 'animate-spin' : ''"/>
            Обновить
          </button>
        </div>

        <div
            class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
          <div class="grid border-b border-slate-100 px-5 py-3"
               style="grid-template-columns: minmax(260px,1fr) 150px 150px 110px 40px">
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Action</div>
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Тип</div>
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Расписание</div>
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Статус</div>
            <div/>
          </div>

          <div v-if="list.loading.value && !list.actions.value.length"
               class="px-5 py-8 text-center text-[13px] text-slate-400">
            Загрузка…
          </div>

          <EmptyState v-else-if="!list.filtered.value.length" title="Actions не найдены"
                      subtitle="Создайте первый action или измените поиск">
            <template #icon>
              <Zap :size="20"/>
            </template>
          </EmptyState>

          <div
              v-for="(item, idx) in list.paged.value"
              :key="item.id"
              class="group grid items-center px-5 py-4 transition-colors hover:bg-slate-50/70"
              :class="idx !== list.paged.value.length - 1 ? 'border-b border-slate-100' : ''"
              style="grid-template-columns: minmax(260px,1fr) 150px 150px 110px 40px"
          >
            <div class="min-w-0 pr-4">
              <div class="flex items-center gap-2">
                <div
                    class="flex h-7 w-7 flex-none items-center justify-center rounded-lg text-[11px] font-bold text-white"
                    :class="item.is_active ? 'bg-blue-600' : 'bg-slate-300'">
                  <Zap :size="13"/>
                </div>
                <span class="truncate text-[13px] font-semibold text-slate-900">{{ item.name }}</span>
              </div>
              <div class="mt-0.5 flex min-w-0 items-center gap-2 pl-9">
                <span class="truncate font-mono text-[11px] text-slate-400">{{ item.key }}</span>
                <span v-if="item.description" class="truncate text-[12px] text-slate-400">{{ item.description }}</span>
              </div>
            </div>

            <div>
                            <span
                                class="inline-flex h-6 items-center rounded-md bg-slate-100 px-2 text-[11px] font-semibold text-slate-600">
                                {{ list.typeLabels.value[item.type] ?? item.type }}
                            </span>
            </div>

            <button class="min-w-0 text-left" @click="scheduleModal.open(item)">
              <div v-if="item.schedule?.enabled"
                   class="flex items-center gap-1.5 text-[12px] font-medium text-blue-700">
                <CalendarClock :size="13"/>
                <span class="font-mono">{{ item.schedule.cron ?? '—' }}</span>
              </div>
              <div v-else class="text-[12px] text-slate-300">—</div>
              <div class="mt-0.5 truncate text-[11px] text-slate-400">
                {{ item.schedule?.next_run_at ? formatDate(item.schedule.next_run_at) : 'Нет запуска' }}
              </div>
            </button>

            <div>
                            <span class="inline-flex h-5 items-center rounded-full px-2 text-[11px] font-semibold"
                                  :class="item.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-400'">
                                {{ item.is_active ? 'Активно' : 'Отключено' }}
                            </span>
            </div>

            <div class="flex justify-end">
              <DropdownMenu>
                <DropdownMenuTrigger as-child>
                  <button
                      class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-slate-400 opacity-0 transition group-hover:opacity-100 hover:bg-slate-100 hover:text-slate-700">
                    <MoreHorizontal :size="15"/>
                  </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-48">
                  <DropdownMenuItem @click="runModal.open(item)">
                    <Play class="mr-2 h-4 w-4 text-slate-400"/>
                    Запустить
                  </DropdownMenuItem>
                  <DropdownMenuItem @click="scheduleModal.open(item)">
                    <Clock3 class="mr-2 h-4 w-4 text-slate-400"/>
                    Расписание
                  </DropdownMenuItem>
                  <DropdownMenuItem @click="modal.openEdit(item)">
                    <Pencil class="mr-2 h-4 w-4 text-slate-400"/>
                    Редактировать
                  </DropdownMenuItem>
                  <DropdownMenuItem @click="toggleActive(item)">
                    <component :is="item.is_active ? ShieldOff : Shield" class="mr-2 h-4 w-4 text-slate-400"/>
                    {{ item.is_active ? 'Отключить' : 'Активировать' }}
                  </DropdownMenuItem>
                  <DropdownMenuSeparator/>
                  <DropdownMenuItem class="text-red-600 focus:bg-red-50 focus:text-red-600" @click="deleteAction(item)">
                    <Trash2 class="mr-2 h-4 w-4"/>
                    Удалить
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
          </div>

          <ListPagination v-model:current-page="list.currentPage.value" :total-pages="list.totalPages.value"
                          :total="list.filtered.value.length" :per-page="list.perPage"/>
        </div>
      </div>
    </div>
  </AppShell>

  <ActionEditorDrawer :modal="modal" :list="list"/>
  <ActionFieldModal :modal="modal"/>
  <ActionRunModal :run-modal="runModal"/>
  <ActionScheduleModal :schedule-modal="scheduleModal"/>
</template>
