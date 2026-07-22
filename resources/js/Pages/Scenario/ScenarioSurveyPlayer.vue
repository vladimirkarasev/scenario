<script setup lang="ts">
import AppShell from '@/layouts/AppShell.vue'
import ScenarioPlayer from '@/modules/scenario/components/player/ScenarioPlayer.vue'
import RunHistory from '@/modules/scenario/components/player/RunHistory.vue'
import {useDashboardNavigation} from '@/composables/useDashboardNavigation'
import {scenarioRunRepository} from '@/modules/scenario/repositories/scenarioRunRepository'
import {formatDateTime} from '@/lib/formatters'
import {Head} from '@inertiajs/vue3'
import {ref} from 'vue'
import {History, X} from 'lucide-vue-next'
import type {RunHistoryEvent, ScenarioRunPayload} from '@/modules/scenario/lib/scenario-player-types'

defineProps<{ runId?: string | null }>()

const {navigationItems} = useDashboardNavigation()

const STATUS_LABELS: Record<string, string> = {
  active: 'В процессе',
  completed: 'Завершён',
  failed: 'Ошибка',
}

const STATUS_PILL: Record<string, string> = {
  active: 'bg-blue-50 text-blue-700',
  completed: 'bg-emerald-50 text-emerald-700',
  failed: 'bg-red-50 text-red-600',
}

const activeRun = ref<ScenarioRunPayload | null>(null)
const historyOpen = ref(false)
const historyEvents = ref<RunHistoryEvent[]>([])
const historyLoading = ref(false)

async function onRunUpdate(run: ScenarioRunPayload | null) {
  activeRun.value = run
  if (historyOpen.value && run) {
    try {
      historyEvents.value = await scenarioRunRepository.history(run.id)
    } catch {
      // История обновится при следующем открытии панели
    }
  }
}

async function toggleHistory() {
  if (historyOpen.value) {
    historyOpen.value = false
    return
  }
  if (!activeRun.value) return
  historyOpen.value = true
  historyLoading.value = true
  try {
    historyEvents.value = await scenarioRunRepository.history(activeRun.value.id)
  } finally {
    historyLoading.value = false
  }
}
</script>

<template>
  <Head :title="activeRun?.scenario_name ?? 'Сессия опроса'"/>

  <AppShell :title="activeRun?.scenario_name ?? 'Сессия опроса'" :navigation-items="navigationItems" flush>
    <div class="flex h-full min-h-0 w-full flex-col overflow-hidden bg-slate-50">
      <!-- Session header -->
      <div class="shrink-0 border-b border-slate-200 bg-white px-6 py-4">
        <div class="flex items-start gap-4">
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2.5">
              <span class="truncate text-[15px] font-bold text-slate-900">
                {{ activeRun?.scenario_name ?? '…' }}
              </span>
              <span
                  v-if="activeRun?.status"
                  class="inline-flex h-5 shrink-0 items-center rounded-full px-2 text-[10px] font-semibold"
                  :class="STATUS_PILL[activeRun.status] ?? 'bg-slate-100 text-slate-500'"
              >
                {{ STATUS_LABELS[activeRun.status] ?? activeRun.status }}
              </span>
              <span
                  v-if="activeRun?.scenario_version_name"
                  class="inline-flex h-5 shrink-0 items-center rounded border border-slate-200 bg-white px-2 font-mono text-[10px] text-slate-400"
              >
                {{ activeRun.scenario_version_name }}
              </span>
            </div>
            <div v-if="activeRun" class="mt-1.5 flex items-center gap-3 text-[12px] text-slate-400">
              <span class="font-mono">{{ activeRun.id }}</span>
              <template v-if="activeRun.created_at">
                <span class="text-slate-300">·</span>
                <span>{{ formatDateTime(activeRun.created_at) }}</span>
              </template>
            </div>
          </div>

          <!-- Кнопка истории -->
          <button
              v-if="activeRun"
              type="button"
              class="inline-flex items-center gap-1.5 rounded-md border px-3 py-1.5 text-[12px] font-medium shadow-sm transition"
              :class="historyOpen
                  ? 'border-blue-200 bg-blue-50 text-blue-700'
                  : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
              @click="toggleHistory"
          >
            <History class="size-3.5"/>
            История
            <span
                v-if="historyEvents.length"
                class="rounded-full px-1.5 py-0.5 text-[10px] font-semibold"
                :class="historyOpen ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-500'"
            >{{ historyEvents.length }}</span>
          </button>
        </div>
      </div>

      <!-- Player + История бок о бок -->
      <div class="flex min-h-0 flex-1">
        <div class="min-h-0 flex-1 overflow-y-auto p-6">
          <div class="mx-auto max-w-2xl">
            <ScenarioPlayer
                :run-id="runId"
                @update:run="onRunUpdate"
            />
          </div>
        </div>

        <Transition
            enter-active-class="transition-opacity duration-150 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-100 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
          <aside
              v-if="historyOpen"
              class="flex h-full w-[23.75rem] flex-none flex-col overflow-hidden border-l border-slate-200 bg-white"
          >
            <div class="flex shrink-0 items-center justify-between border-b border-slate-100 px-4 py-3">
              <span class="text-sm font-semibold text-slate-900">История прохождения</span>
              <button
                  type="button"
                  class="rounded-md p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                  @click="historyOpen = false"
              >
                <X class="size-4"/>
              </button>
            </div>
            <div class="flex-1 overflow-y-auto px-4 py-4">
              <div v-if="historyLoading" class="flex items-center justify-center py-10">
                <div class="h-5 w-5 animate-spin rounded-full border-2 border-slate-300 border-t-slate-600"/>
              </div>
              <RunHistory v-else :events="historyEvents"/>
            </div>
          </aside>
        </Transition>
      </div>
    </div>
  </AppShell>
</template>
