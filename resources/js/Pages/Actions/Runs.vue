<script setup lang="ts">
import AppShell from '@/layouts/AppShell.vue'
import EmptyState from '@/components/EmptyState.vue'
import PageHeader from '@/components/PageHeader.vue'
import SearchInput from '@/components/SearchInput.vue'
import AppEditorDrawer from '@/components/AppEditorDrawer.vue'
import {Button} from '@/components/ui/button'
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table'
import {useDashboardNavigation} from '@/composables/useDashboardNavigation'
import {useActionRunList} from '@/modules/actions/composables/useActionRunList'
import type {ActionRun} from '@/modules/actions/types/action'
import {Head} from '@inertiajs/vue3'
import {CalendarClock, CheckCircle2, ChevronRight, Clock3, Loader2, Play, RefreshCw, Zap} from 'lucide-vue-next'
import {computed, ref} from 'vue'

const {navigationItems} = useDashboardNavigation()

const {runs, loading, error, search, statusFilter, filtered, counts, load} = useActionRunList()

const selected = ref<ActionRun | null>(null)
const showDetails = ref(false)

function openDetails(run: ActionRun): void {
  selected.value = run
  showDetails.value = true
}

function statusClass(status: string): string {
  if (status === 'success') return 'bg-emerald-50 text-emerald-700'
  if (status === 'failed') return 'bg-red-50 text-red-700'
  if (status === 'running') return 'bg-blue-50 text-blue-700'
  if (status === 'skipped') return 'bg-amber-50 text-amber-700'
  return 'bg-slate-100 text-slate-500'
}

function fmt(iso: string | null | undefined): string {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('ru-RU', {dateStyle: 'short', timeStyle: 'short'})
}

function duration(run: ActionRun): string {
  if (typeof run.duration_ms !== 'number') return '—'
  if (run.duration_ms < 1000) return `${run.duration_ms} мс`
  return `${(run.duration_ms / 1000).toFixed(1)} с`
}

function pretty(value: unknown): string {
  if (value === null || value === undefined) return '—'
  try {
    return JSON.stringify(value, null, 2)
  } catch {
    return String(value)
  }
}

const messageBox = computed<{request: unknown; response: unknown} | null>(() => {
  const output = selected.value?.output
  if (!output || typeof output !== 'object' || !('request' in output)) return null
  const {request, ...response} = output as Record<string, unknown>
  return {request, response}
})
</script>

<template>
  <Head title="История запусков" />

  <AppShell title="История запусков" :navigation-items="navigationItems">
    <div class="min-h-full bg-slate-50">
      <div class="mx-auto max-w-6xl px-6 py-8">
        <PageHeader
            title="История запусков"
            subtitle="Выполненные и упавшие задачи actions."
        >
          <template #actions>
            <a href="/actions"
               class="inline-flex h-9 shrink-0 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-[13px] font-medium text-slate-700 transition hover:bg-slate-50">
              <Zap :size="15" />
              Действия
            </a>
            <a href="/actions/schedules"
               class="inline-flex h-9 shrink-0 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-[13px] font-medium text-slate-700 transition hover:bg-slate-50">
              <CalendarClock :size="15" />
              Расписания
            </a>
          </template>
        </PageHeader>

        <div v-if="error" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-[13px] text-red-700">
          {{ error }}
        </div>

        <div class="mb-6 grid gap-4 md:grid-cols-4">
          <button
              type="button"
              class="rounded-xl border px-5 py-4 text-left shadow-[0_1px_2px_rgba(15,23,42,0.04)] transition"
              :class="statusFilter === 'all' ? 'border-slate-300 bg-white' : 'border-slate-200 bg-white hover:bg-slate-50'"
              @click="statusFilter = 'all'"
          >
            <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Всего</div>
            <div class="mt-1.5 text-3xl font-bold tabular-nums text-slate-900">{{ counts.total }}</div>
          </button>
          <button
              type="button"
              class="rounded-xl border px-5 py-4 text-left shadow-[0_1px_2px_rgba(15,23,42,0.04)] transition"
              :class="statusFilter === 'success' ? 'border-emerald-300 bg-emerald-50' : 'border-emerald-100 bg-emerald-50 hover:bg-emerald-100/50'"
              @click="statusFilter = 'success'"
          >
            <div class="text-[11px] font-semibold uppercase tracking-wider text-emerald-600">Успешно</div>
            <div class="mt-1.5 text-3xl font-bold tabular-nums text-emerald-700">{{ counts.success }}</div>
          </button>
          <button
              type="button"
              class="rounded-xl border px-5 py-4 text-left shadow-[0_1px_2px_rgba(15,23,42,0.04)] transition"
              :class="statusFilter === 'failed' ? 'border-red-300 bg-red-50' : 'border-red-100 bg-red-50 hover:bg-red-100/50'"
              @click="statusFilter = 'failed'"
          >
            <div class="text-[11px] font-semibold uppercase tracking-wider text-red-600">Ошибки</div>
            <div class="mt-1.5 text-3xl font-bold tabular-nums text-red-700">{{ counts.failed }}</div>
          </button>
          <button
              type="button"
              class="rounded-xl border px-5 py-4 text-left shadow-[0_1px_2px_rgba(15,23,42,0.04)] transition"
              :class="statusFilter === 'skipped' ? 'border-amber-300 bg-amber-50' : 'border-amber-100 bg-amber-50 hover:bg-amber-100/50'"
              @click="statusFilter = 'skipped'"
          >
            <div class="text-[11px] font-semibold uppercase tracking-wider text-amber-600">Пропущено</div>
            <div class="mt-1.5 text-3xl font-bold tabular-nums text-amber-700">{{ counts.skipped }}</div>
          </button>
        </div>

        <div class="mb-4 flex items-center gap-3">
          <div class="flex-1">
            <SearchInput v-model="search" placeholder="Поиск по run ID, action, ошибке…" />
          </div>
          <Button variant="outline" class="gap-2" :disabled="loading" @click="load">
            <Loader2 v-if="loading" :size="14" class="animate-spin" />
            <RefreshCw v-else :size="14" />
            Обновить
          </Button>
        </div>

        <div
            class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead class="w-20">Run</TableHead>
                <TableHead>Action</TableHead>
                <TableHead class="w-28">Статус</TableHead>
                <TableHead class="w-24">Попытка</TableHead>
                <TableHead class="w-28">Длит.</TableHead>
                <TableHead class="w-40">Запущен</TableHead>
                <TableHead>Результат</TableHead>
                <TableHead class="w-10" />
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow v-for="run in filtered" :key="run.id" class="cursor-pointer hover:bg-slate-50"
                        @click="openDetails(run)">
                <TableCell class="font-mono text-[12px] text-slate-500">#{{ run.id }}</TableCell>
                <TableCell class="text-[12px] font-medium text-slate-700">
{{
                    run.action_name || run.action_id
                  }}
                </TableCell>
                <TableCell>
                                    <span
                                        class="inline-flex h-5 items-center rounded-full px-2 text-[11px] font-semibold"
                                        :class="statusClass(run.status)">
                                        {{ run.status }}
                                    </span>
                </TableCell>
                <TableCell class="text-[12px] text-slate-500">{{ run.attempts_count ?? 1 }}</TableCell>
                <TableCell class="text-[12px] text-slate-500">{{ duration(run) }}</TableCell>
                <TableCell class="text-[12px] text-slate-500">{{ fmt(run.started_at) }}</TableCell>
                <TableCell class="text-[12px] text-slate-400 truncate max-w-md">
{{
                    run.reason || run.error || '—'
                  }}
                </TableCell>
                <TableCell>
                  <ChevronRight :size="14" class="text-slate-300" />
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
          <EmptyState v-if="!filtered.length && !loading" title="Запусков нет"
                      subtitle="Запустите action или измените фильтр">
            <template #icon>
              <Play :size="20" />
            </template>
          </EmptyState>
          <div v-if="loading && !runs.length" class="px-5 py-8 text-center text-[13px] text-slate-400">
            <CheckCircle2 class="mx-auto mb-2 h-5 w-5 text-slate-300" />
            Загрузка…
          </div>
        </div>
      </div>
    </div>
  </AppShell>

  <AppEditorDrawer
      v-model:open="showDetails"
      :title="`Run #${selected?.id ?? ''}`"
      :subtitle="selected?.action_name || selected?.action_id || ''"
      width-class="!w-[640px] !max-w-[90vw]"
      icon-bg-class="bg-slate-100"
      :show-footer="false"
  >
    <template #icon>
      <Clock3 class="size-3.5 text-slate-500" />
    </template>

    <div v-if="selected" class="flex-1 space-y-4 overflow-y-auto px-6 py-5">
      <div class="grid gap-3 md:grid-cols-2">
        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
          <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Статус</div>
          <div class="mt-1">
                            <span class="inline-flex h-5 items-center rounded-full px-2 text-[11px] font-semibold"
                                  :class="statusClass(selected.status)">
                                {{ selected.status }}
                            </span>
          </div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
          <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Длительность</div>
          <div class="mt-1 text-[13px] font-semibold text-slate-700">{{ duration(selected) }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
          <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Попыток</div>
          <div class="mt-1 text-[13px] font-semibold text-slate-700">{{ selected.attempts_count ?? 1 }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
          <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Запущен</div>
          <div class="mt-1 text-[13px] font-semibold text-slate-700">{{ fmt(selected.started_at) }}</div>
        </div>
      </div>

      <div v-if="selected.error" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3">
        <div class="text-[10px] font-semibold uppercase tracking-wider text-red-600">Ошибка</div>
        <div class="mt-1 font-mono text-[12px] text-red-700">{{ selected.error }}</div>
      </div>

      <div>
        <div class="mb-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Input</div>
        <pre
            class="max-h-72 overflow-auto rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-[11px] text-slate-700">{{
            pretty(selected.input)
          }}</pre>
      </div>

      <template v-if="messageBox">
        <div>
          <div class="mb-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Отправлено</div>
          <pre
              class="max-h-72 overflow-auto rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-[11px] text-slate-700">{{
              pretty(messageBox.request)
            }}</pre>
        </div>

        <div>
          <div class="mb-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Получено</div>
          <pre
              class="max-h-72 overflow-auto rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-[11px] text-slate-700">{{
              pretty(messageBox.response)
            }}</pre>
        </div>
      </template>

      <div v-else>
        <div class="mb-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Output</div>
        <pre
            class="max-h-72 overflow-auto rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-[11px] text-slate-700">{{
            pretty(selected.output)
          }}</pre>
      </div>
    </div>
  </AppEditorDrawer>
</template>
