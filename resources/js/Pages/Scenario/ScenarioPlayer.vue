<script setup lang="ts">
import AppShell from '@/layouts/AppShell.vue'
import CopyButton from '@/components/CopyButton.vue'
import ScenarioPlayer from '@/modules/scenario/components/player/ScenarioPlayer.vue'
import RunHistory from '@/modules/scenario/components/player/RunHistory.vue'
import {useDashboardNavigation} from '@/composables/useDashboardNavigation'
import {scenarioRunRepository} from '@/modules/scenario/repositories/scenarioRunRepository'
import {Head} from '@inertiajs/vue3'
import {computed, ref} from 'vue'
import {History, X} from 'lucide-vue-next'
import type {RunHistoryEvent, ScenarioRunPayload, ScenarioRunParty} from '@/modules/scenario/lib/scenario-player-types'

defineProps<{
  scenarioId?: string | null
  scenarioVersionId?: string | null
  runId?: string | null
}>()

const {navigationItems} = useDashboardNavigation()

const run = ref<ScenarioRunPayload | null>(null)
const historyOpen = ref(false)
const historyEvents = ref<RunHistoryEvent[]>([])
const historyLoading = ref(false)

async function toggleHistory() {
  if (historyOpen.value) {
    historyOpen.value = false
    return
  }
  if (!run.value) return
  historyOpen.value = true
  historyLoading.value = true
  try {
    historyEvents.value = await scenarioRunRepository.history(run.value.id)
  } finally {
    historyLoading.value = false
  }
}

async function onRunUpdate(nextRun: ScenarioRunPayload | null) {
  run.value = nextRun
  if (historyOpen.value && nextRun) {
    try {
      historyEvents.value = await scenarioRunRepository.history(nextRun.id)
    } catch {
      // История обновится при следующем открытии панели
    }
  }
}

const scenarioName = computed(() => run.value?.scenario_name || run.value?.current_scenario_name || 'Сценарий')

function partyName(party?: ScenarioRunParty | null): string | null {
  if (!party) return null
  return party.fio || party.name || party.login || `#${party.id}`
}

const operatorName = computed(() => partyName(run.value?.operator))

const clientLabel = computed(() => {
  const client = run.value?.client
  if (!client) return null
  const parts = [client.fio, client.phone].filter((v): v is string => Boolean(v))
  return parts.length ? parts.join(' · ') : null
})

const createdAt = computed(() => {
  if (!run.value?.created_at) return null
  return new Date(run.value.created_at).toLocaleString('ru-RU', {
    day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
  })
})

</script>

<template>
  <Head title="Сценарий"/>

  <AppShell title="Сценарий" description="" :navigation-items="navigationItems" flush>
    <div class="flex h-full min-h-0 w-full">
      <div class="survey-content min-h-0 flex-1 overflow-auto bg-[#F7F8FA]">
        <!-- Шапка прогона -->
        <div
            v-if="run"
            class="sticky top-0 z-20 border-b border-slate-200/80 bg-white/85 backdrop-blur supports-[backdrop-filter]:bg-white/70"
        >
          <div class="mx-auto flex max-w-2xl items-center justify-between gap-4 px-4 py-2.5">
            <div class="min-w-0">
              <div class="truncate text-[14px] font-semibold tracking-[-0.01em] text-slate-900">
                {{ scenarioName }}
              </div>
              <CopyButton
                  :text="run.id"
                  :label="run.id"
                  :copied-label="run.id"
                  class="mt-0.5 inline-flex items-center gap-1 font-mono text-[10.5px] text-slate-400 transition hover:text-slate-600"
                  title="Скопировать UUID"
              />
            </div>

            <div class="flex shrink-0 flex-wrap items-center justify-end gap-x-4 gap-y-0.5 text-[11px] leading-5">
              <div v-if="operatorName" class="whitespace-nowrap">
                <span class="text-slate-400">Оператор:</span>
                <span class="ml-1 font-medium text-slate-700">{{ operatorName }}</span>
              </div>
              <div v-if="clientLabel" class="whitespace-nowrap">
                <span class="text-slate-400">Клиент:</span>
                <span class="ml-1 font-medium text-slate-700">{{ clientLabel }}</span>
              </div>
              <div v-if="createdAt" class="whitespace-nowrap">
                <span class="text-slate-400">Создан:</span>
                <span class="ml-1 font-medium text-slate-700">{{ createdAt }}</span>
              </div>

              <!-- Кнопка истории -->
              <button
                  type="button"
                  class="inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-[11px] font-medium shadow-sm transition"
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
        </div>

        <div class="mx-auto w-full max-w-2xl px-4 py-8 md:py-12">
          <ScenarioPlayer
              :run-id="runId ?? null"
              :scenario-id="scenarioId ?? null"
              :scenario-version-id="scenarioVersionId ?? null"
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
  </AppShell>
</template>

<style scoped>
.survey-content {
  background-image: radial-gradient(ellipse 120% 80% at 50% -10%, rgba(59, 130, 246, 0.05) 0%, transparent 60%);
}
</style>
