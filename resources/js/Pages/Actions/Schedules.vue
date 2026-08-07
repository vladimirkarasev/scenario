<script setup lang="ts">
import AppShell from '@/layouts/AppShell.vue'
import EmptyState from '@/components/EmptyState.vue'
import PageHeader from '@/components/PageHeader.vue'
import SearchInput from '@/components/SearchInput.vue'
import {Button} from '@/components/ui/button'
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table'
import {useDashboardNavigation} from '@/composables/useDashboardNavigation'
import {useActionScheduleList} from '@/modules/actions/composables/useActionScheduleList'
import {Head} from '@inertiajs/vue3'
import {Calendar, CalendarClock, Clock3, Loader2, Play, RefreshCw, Zap} from 'lucide-vue-next'

const {navigationItems} = useDashboardNavigation()
const {items, loading, error, search, showDisabled, filtered, counts, load} = useActionScheduleList()

function fmt(iso: string | null | undefined): string {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('ru-RU', {dateStyle: 'short', timeStyle: 'short'})
}

function relativeUntil(iso: string | null | undefined): string {
  if (!iso) return ''
  const diffMs = new Date(iso).getTime() - Date.now()
  if (diffMs < 0) return 'просрочено'
  const m = Math.floor(diffMs / 60000)
  if (m < 1) return 'меньше минуты'
  if (m < 60) return `через ${m} мин`
  const h = Math.floor(m / 60)
  if (h < 24) return `через ${h} ч`
  const d = Math.floor(h / 24)
  return `через ${d} д`
}
</script>

<template>
  <Head title="Расписания action" />

  <AppShell title="Расписания action" :navigation-items="navigationItems">
    <div class="app-page">
      <div class="app-page-container max-w-6xl">
        <PageHeader title="Расписания" subtitle="Action, которые запускаются автоматически по cron-выражению.">
          <template #actions>
            <a href="/actions"
               class="inline-flex h-9 shrink-0 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-[13px] font-medium text-slate-700 transition hover:bg-slate-50">
              <Zap :size="15" />
              Действия
            </a>
            <a href="/actions/runs"
               class="inline-flex h-9 shrink-0 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-[13px] font-medium text-slate-700 transition hover:bg-slate-50">
              <Clock3 :size="15" />
              История запусков
            </a>
          </template>
        </PageHeader>

        <div v-if="error" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-[13px] text-red-700">
          {{ error }}
        </div>

        <div class="mb-6 grid gap-4 md:grid-cols-3">
          <div class="rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
            <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Всего расписаний</div>
            <div class="mt-1.5 text-3xl font-bold tabular-nums text-slate-900">{{ counts.total }}</div>
          </div>
          <div
              class="rounded-xl border border-emerald-100 bg-emerald-50 px-5 py-4 shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
            <div class="text-[11px] font-semibold uppercase tracking-wider text-emerald-600">Активные</div>
            <div class="mt-1.5 text-3xl font-bold tabular-nums text-emerald-700">{{ counts.enabled }}</div>
          </div>
          <div class="rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
            <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Отключены</div>
            <div class="mt-1.5 text-3xl font-bold tabular-nums text-slate-500">{{ counts.disabled }}</div>
          </div>
        </div>

        <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div class="max-w-sm flex-1">
            <SearchInput v-model="search" placeholder="Поиск по action или cron..." />
          </div>
          <div class="flex items-center gap-3">
            <label class="flex cursor-pointer items-center gap-2 text-[12px] text-slate-600">
              <input v-model="showDisabled" type="checkbox" class="size-3.5 rounded" />
              Показывать отключённые
            </label>
            <Button variant="outline" class="gap-2" :disabled="loading" @click="load">
              <Loader2 v-if="loading" :size="14" class="animate-spin" />
              <RefreshCw v-else :size="14" />
              Обновить
            </Button>
          </div>
        </div>

        <div class="app-panel">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Action</TableHead>
                <TableHead class="w-44">Cron</TableHead>
                <TableHead class="w-36">Timezone</TableHead>
                <TableHead class="w-44">Следующий запуск</TableHead>
                <TableHead class="w-40">Последний запуск</TableHead>
                <TableHead class="w-24">Статус</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow v-for="s in filtered" :key="s.id">
                <TableCell>
                  <div class="flex items-center gap-2">
                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-white"
                         :class="s.action?.is_active ? 'bg-blue-600' : 'bg-slate-300'">
                      <Zap :size="13" />
                    </div>
                    <div class="min-w-0">
                      <div class="truncate text-[13px] font-semibold text-slate-900">{{ s.action?.name ?? '—' }}</div>
                      <div class="truncate font-mono text-[11px] text-slate-400">{{ s.action?.code ?? '—' }}</div>
                    </div>
                  </div>
                </TableCell>
                <TableCell class="font-mono text-[12px] text-slate-700">{{ s.cron ?? '—' }}</TableCell>
                <TableCell class="text-[12px] text-slate-500">{{ s.timezone }}</TableCell>
                <TableCell>
                  <div class="flex items-center gap-1.5 text-[12px] text-slate-700">
                    <CalendarClock :size="13" class="text-slate-400" />
                    {{ fmt(s.next_run_at) }}
                  </div>
                  <div class="text-[11px] text-slate-400">{{ relativeUntil(s.next_run_at) }}</div>
                </TableCell>
                <TableCell>
                  <div class="flex items-center gap-1.5 text-[12px] text-slate-500">
                    <Calendar :size="13" class="text-slate-400" />
                    {{ fmt(s.last_run_at) }}
                  </div>
                </TableCell>
                <TableCell>
                  <span v-if="s.enabled"
                        class="inline-flex h-5 items-center rounded-full bg-emerald-50 px-2 text-[11px] font-semibold text-emerald-700">Активно</span>
                  <span v-else
                        class="inline-flex h-5 items-center rounded-full bg-slate-100 px-2 text-[11px] font-semibold text-slate-500">Отключено</span>
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
          <EmptyState v-if="!filtered.length && !loading" title="Расписаний нет"
                      subtitle="Добавьте расписание action на странице действий">
            <template #icon>
              <CalendarClock :size="20" />
            </template>
          </EmptyState>
          <div v-if="loading && !items.length" class="px-5 py-8 text-center text-[13px] text-slate-400">
            <Play class="mx-auto mb-2 h-5 w-5 text-slate-300" />
            Загрузка…
          </div>
        </div>
      </div>
    </div>
  </AppShell>
</template>
