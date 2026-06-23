<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue'
import ListPagination from '@/components/ListPagination.vue'
import {Check, ClipboardList, Copy} from 'lucide-vue-next'
import {Link} from '@inertiajs/vue3'
import {formatDateTime} from '@/lib/formatters'
import type {ScenarioActor, ScenarioRunListItem} from '@/modules/scenario/types/scenario'

defineProps<{
  runs: ScenarioRunListItem[]
  loading: boolean
  currentPage: number
  totalPages: number
  total: number
  copiedVersionId: string | null
}>()

defineEmits<{
  'update:currentPage': [page: number]
  copyVersion: [id: string]
}>()

const STATUS_LABEL: Record<string, string> = {
  active: 'В процессе',
  completed: 'Завершён',
  failed: 'Ошибка',
}

const STATUS_STYLE: Record<string, string> = {
  active: 'bg-blue-100 text-blue-700',
  completed: 'bg-emerald-100 text-emerald-700',
  failed: 'bg-red-100 text-red-600',
}

const AVATAR_COLORS = ['#2563EB', '#059669', '#7C3AED', '#DC2626', '#D97706', '#0891B2']

function actorName(a: ScenarioActor | null): string {
  if (!a) return '—'
  return a.fio ?? a.name ?? a.login ?? `#${a.id}`
}

function completedAt(run: ScenarioRunListItem): string {
  return run.status === 'active' ? '—' : (formatDateTime(run.updated_at) ?? '—')
}

function initials(name: string): string {
  return name.split(' ').slice(0, 2).map(s => s[0] ?? '').join('').toUpperCase() || '?'
}

function avatarColor(name: string): string {
  let h = 0
  for (let i = 0; i < name.length; i++) h = (h * 31 + name.charCodeAt(i)) % AVATAR_COLORS.length
  return AVATAR_COLORS[Math.abs(h)]
}
</script>

<template>
  <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
    <div
        class="grid border-b border-slate-100 px-5 py-3"
        style="grid-template-columns: 1fr 110px 200px 140px 140px 90px"
    >
      <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Сценарий</div>
      <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Статус</div>
      <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Пользователь</div>
      <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Начат</div>
      <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Завершён</div>
      <div/>
    </div>

    <div v-if="loading" class="divide-y divide-slate-100">
      <div
          v-for="i in 10"
          :key="i"
          class="grid items-center px-5 py-4"
          style="grid-template-columns: 1fr 110px 200px 140px 140px 90px"
      >
        <div class="space-y-1.5 pr-6">
          <div class="h-3.5 w-48 animate-pulse rounded bg-slate-100"/>
          <div class="h-2.5 w-32 animate-pulse rounded bg-slate-100"/>
        </div>
        <div class="h-5 w-16 animate-pulse rounded-full bg-slate-100"/>
        <div class="h-3.5 w-28 animate-pulse rounded bg-slate-100"/>
        <div class="h-3.5 w-24 animate-pulse rounded bg-slate-100"/>
        <div class="h-3.5 w-24 animate-pulse rounded bg-slate-100"/>
        <div class="h-7 w-16 animate-pulse rounded-lg bg-slate-100"/>
      </div>
    </div>

    <EmptyState v-else-if="!runs.length" title="Опросов не найдено" subtitle="Попробуйте изменить фильтры">
      <template #icon>
        <ClipboardList :size="18"/>
      </template>
    </EmptyState>

    <div v-else class="divide-y divide-slate-100">
      <div
          v-for="run in runs"
          :key="run.id"
          class="grid items-center px-5 py-3.5 transition-colors hover:bg-slate-50/70"
          style="grid-template-columns: 1fr 110px 200px 140px 140px 90px"
      >
        <div class="min-w-0 pr-4">
          <div class="truncate text-[13px] font-semibold text-slate-900">
            {{ run.scenario_name ?? `Сценарий #${run.scenario_id.slice(0, 8)}` }}
          </div>
          <button
              v-if="run.scenario_version_id"
              type="button"
              class="mt-0.5 inline-flex items-center gap-1 truncate font-mono text-[10px] text-slate-400 transition hover:text-blue-600"
              :title="`Скопировать ${run.scenario_version_id}`"
              @click="$emit('copyVersion', run.scenario_version_id)"
          >
            <span class="truncate">версия: {{
                run.scenario_version_name || 'v1'
              }}-{{ run.scenario_version_id.slice(0, 8) }}</span>
            <Check v-if="copiedVersionId === run.scenario_version_id" :size="10" class="shrink-0 text-emerald-500"/>
            <Copy v-else :size="10" class="shrink-0 opacity-50"/>
          </button>
          <div class="mt-0.5 font-mono text-[10px] text-slate-400">{{ run.id }}</div>
        </div>

        <div>
                    <span
                        class="inline-flex h-5 items-center rounded px-1.5 text-[10px] font-semibold"
                        :class="STATUS_STYLE[run.status] ?? 'bg-slate-100 text-slate-600'"
                    >
                        {{ STATUS_LABEL[run.status] ?? run.status }}
                    </span>
        </div>

        <div class="flex items-center gap-2 pr-4">
                    <span
                        v-if="run.created_by"
                        class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[9px] font-bold text-white"
                        :style="{ background: avatarColor(run.created_by.name ?? run.created_by.login ?? '') }"
                    >
                        {{ initials(run.created_by.name ?? run.created_by.login ?? '') }}
                    </span>
          <span class="truncate text-[12px] text-slate-700">{{ actorName(run.created_by) }}</span>
        </div>

        <div class="text-[12px] tabular-nums text-slate-500">{{ formatDateTime(run.created_at) }}</div>
        <div class="text-[12px] tabular-nums text-slate-500">{{ completedAt(run) }}</div>

        <div class="flex justify-end">
          <Link
              :href="route('surveys.run', run.id)"
              class="inline-flex h-7 items-center rounded-lg px-3 text-[12px] font-medium transition"
              :class="run.status === 'active'
                            ? 'bg-blue-600 text-white hover:bg-blue-700'
                            : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
          >
            {{ run.status === 'active' ? 'Продолжить' : 'Просмотр' }}
          </Link>
        </div>
      </div>
    </div>

    <ListPagination
        :current-page="currentPage"
        :total-pages="totalPages"
        :total="total"
        :per-page="20"
        @update:current-page="$emit('update:currentPage', $event)"
    />
  </div>
</template>
