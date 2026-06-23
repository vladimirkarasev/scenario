<script setup lang="ts">
import AppShell from '@/layouts/AppShell.vue'
import ScenarioPlayer from '@/modules/scenario/components/player/ScenarioPlayer.vue'
import type {ScenarioRunPayload, ScenarioRunStatus} from '@/modules/scenario/lib/scenario-player-types'
import {scenarioRunRepository} from '@/modules/scenario/repositories/scenarioRunRepository'
import {useStartScenarioListener} from '@/modules/scenario/composables/useStartScenarioListener'
import type {ScenarioRunListItem} from '@/modules/scenario/types/scenario'
import {Skeleton} from '@/components/ui/skeleton'
import {useDashboardNavigation} from '@/composables/useDashboardNavigation'
import {formatDateTime} from '@/lib/formatters'
import {Head, Link, router} from '@inertiajs/vue3'
import type {Subscription} from 'centrifuge'
import {ChevronLeft, ChevronRight, ClipboardList, Search, X} from 'lucide-vue-next'
import {computed, onBeforeUnmount, onMounted, onUnmounted, ref, watch} from 'vue'

const props = defineProps<{
  initialRunId?: string | null
  initialScenarioId?: string | null
}>()

const {navigationItems} = useDashboardNavigation()

const runs = ref<ScenarioRunListItem[]>([])
const runsTotal = ref(0)
const runsPage = ref(1)
const runsLastPage = ref(1)
const runsFrom = ref<number | null>(null)
const runsTo = ref<number | null>(null)
const runsLoading = ref(false)
const runSubscriptions = ref<Subscription[]>([])

const search = ref('')
const runsStatusFilter = ref<string>('active')
const selectedScenarioId = ref<string | null>(null)
const selectedScenarioVersionId = ref<string | null>(null)
const selectedRunId = ref<string | null>(null)
const playerKey = ref(0)
const activeRun = ref<ScenarioRunPayload | null>(null)
const navigateOnRun = ref(false)

useStartScenarioListener((msg) => {
  router.visit(msg.url)
})

let searchTimer: ReturnType<typeof setTimeout> | null = null

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

const RUN_STATUS_FILTERS = [
  {value: '', label: 'Все'},
  {value: 'active', label: 'В процессе'},
  {value: 'completed', label: 'Завершённые'},
]

onMounted(() => {
  loadRuns()
  if (props.initialRunId) {
    pickRun(props.initialRunId)
  } else if (props.initialScenarioId) {
    navigateOnRun.value = true
    selectedScenarioId.value = props.initialScenarioId
    selectedScenarioVersionId.value = null
    playerKey.value++
  }
})

async function loadRuns() {
  runsLoading.value = true
  try {
    const qs = new URLSearchParams()
    qs.set('page', String(runsPage.value))
    if (search.value.trim()) qs.set('search', search.value.trim())
    if (runsStatusFilter.value === 'active') qs.set('status', 'active')
    if (runsStatusFilter.value === 'completed') qs.set('finished', '1')

    const page = await scenarioRunRepository.list(qs)
    runs.value = page.runs
    runsTotal.value = page.pagination.total
    runsLastPage.value = page.pagination.last_page
    runsFrom.value = page.pagination.from
    runsTo.value = page.pagination.to
  } finally {
    runsLoading.value = false
  }
}

onUnmounted(() => runSubscriptions.value.forEach(sub => sub.unsubscribe()))

watch(runsPage, loadRuns)
watch(search, () => {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => resetRunsPagination(), 300)
})
onBeforeUnmount(() => {
  if (searchTimer) clearTimeout(searchTimer)
})

function setRunsStatus(status: string) {
  runsStatusFilter.value = status
  resetRunsPagination()
}

function clearSearch() {
  search.value = ''
  resetRunsPagination()
}

function resetRunsPagination() {
  if (runsPage.value === 1) {
    loadRuns();
    return
  }
  runsPage.value = 1
}

const totalPages = computed(() => Math.max(1, runsLastPage.value))
const runPageNumbers = computed(() => {
  const total = totalPages.value
  const current = runsPage.value
  const pages: number[] = []
  for (let p = Math.max(1, current - 2); p <= Math.min(total, current + 2); p++) pages.push(p)
  return pages
})

function prevPage() {
  goToPage(runsPage.value - 1)
}

function nextPage() {
  goToPage(runsPage.value + 1)
}

function goToPage(page: number) {
  const target = Math.min(Math.max(1, page), totalPages.value)
  if (target !== runsPage.value) runsPage.value = target
}

function pickRun(id: string) {
  const listItem = runs.value.find(r => r.id === id) ?? null
  selectedScenarioId.value = null
  selectedScenarioVersionId.value = null
  selectedRunId.value = id
  activeRun.value = listItem ? listItemToRunPayload(listItem) : null
  playerKey.value++
  history.replaceState(null, '', route('workspace.run', id))
}

function listItemToRunPayload(item: ScenarioRunListItem): ScenarioRunPayload {
  return {
    id: item.id,
    number: item.number,
    number_formatted: item.number_formatted,
    scenario_id: item.scenario_id,
    scenario_name: item.scenario_name,
    scenario_version_id: item.scenario_version_id,
    scenario_version_name: null,
    current_node_id: item.current_node_id,
    status: item.status as ScenarioRunStatus,
    created_at: item.created_at,
    context: {},
    current_node: null,
    rendered: null,
    steps: [],
  }
}

function onRunUpdate(run: ScenarioRunPayload | null) {
  activeRun.value = run
  if (navigateOnRun.value && run?.id) {
    navigateOnRun.value = false
    router.visit(route('workspace.run', run.id))
  }
}

const hasSelection = computed(() => selectedScenarioId.value !== null || selectedRunId.value !== null)

function runStatusAt(run: ScenarioRunListItem): string {
  if (run.status === 'completed' || run.status === 'failed') {
    return run.updated_at ? formatDateTime(run.updated_at) : '—'
  }
  return run.created_at ? formatDateTime(run.created_at) : '—'
}
</script>

<template>
  <Head title="Опросы"/>

  <AppShell title="Опросы" :navigation-items="navigationItems" flush>
    <div class="flex h-full min-h-0 w-full overflow-hidden">
      <!-- ══ Left sidebar ══════════════════════════════════════════════ -->
      <aside class="flex w-72 flex-none flex-col overflow-hidden border-r border-slate-200 bg-white">
        <!-- Tab bar -->
        <div class="shrink-0 px-3 pt-3 pb-2">
          <div class="flex rounded-lg bg-slate-100 p-0.5">
            <Link
                :href="route('workspace.scenarios')"
                class="flex-1 rounded-md py-1.5 text-center text-[12px] font-semibold text-slate-500 transition-all hover:text-slate-700"
            >
              Сценарии
            </Link>
            <Link
                :href="route('surveys')"
                class="flex-1 rounded-md bg-white py-1.5 text-center text-[12px] font-semibold text-slate-900 shadow-sm"
            >
              Опросы
            </Link>
          </div>
        </div>

        <!-- Status filter pills -->
        <div class="flex gap-1 px-4 pb-3 pt-3">
          <button
              v-for="opt in RUN_STATUS_FILTERS"
              :key="opt.value"
              class="h-6 rounded-full px-2.5 text-[11px] font-semibold transition-colors whitespace-nowrap"
              :class="runsStatusFilter === opt.value
              ? 'bg-blue-600 text-white'
              : 'bg-slate-100 text-slate-500 hover:text-slate-800'"
              @click="setRunsStatus(opt.value)"
          >
            {{ opt.label }}
          </button>
        </div>

        <!-- Search -->
        <div class="px-3 pb-3">
          <div class="relative">
            <Search :size="14" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"/>
            <input
                v-model="search"
                type="text"
                placeholder="Поиск сессий..."
                class="h-9 w-full rounded-lg border border-slate-200 bg-slate-50 pl-9 pr-8 text-[13px] text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-blue-400 focus:bg-white focus:ring-2 focus:ring-blue-100"
            />
            <button
                v-if="search"
                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 transition hover:text-slate-700"
                @click="clearSearch"
            >
              <X :size="14"/>
            </button>
          </div>
        </div>

        <!-- Sessions list -->
        <div class="flex-1 overflow-y-auto px-3 pb-3">
          <div v-if="runsLoading" class="space-y-2">
            <Skeleton v-for="i in 6" :key="i" class="h-[88px] w-full rounded-xl"/>
          </div>

          <div v-else-if="!runs.length" class="flex flex-col items-center py-14 text-center">
            <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-slate-400">
              <ClipboardList :size="20"/>
            </div>
            <div class="text-[13px] font-semibold text-slate-900">Нет сессий</div>
            <div class="mt-1 text-[12px] text-slate-400">
              {{ runsStatusFilter || search ? 'Ничего не найдено' : 'Сессий пока нет' }}
            </div>
          </div>

          <div v-else class="flex flex-col gap-1.5">
            <button
                v-for="run in runs"
                :key="run.id"
                class="w-full rounded-xl border p-3.5 text-left transition-all"
                :class="selectedRunId === run.id
                ? 'border-blue-300 bg-blue-50/70 shadow-[0_0_0_1px_#93c5fd]'
                : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50/80'"
                @click="pickRun(run.id)"
            >
              <div class="mb-2 flex items-start justify-between gap-2">
                <span class="line-clamp-2 text-[13px] font-semibold leading-snug text-slate-900">
                  {{ run.scenario_name ?? `Сценарий #${run.scenario_id}` }}
                </span>
                <span
                    class="inline-flex h-5 shrink-0 items-center rounded-full px-2 text-[10px] font-semibold"
                    :class="STATUS_PILL[run.status] ?? 'bg-slate-100 text-slate-500'"
                >
                  {{ STATUS_LABELS[run.status] ?? run.status }}
                </span>
              </div>
              <div class="flex items-center justify-between gap-2 text-[11px] text-slate-400">
                <span>{{ runStatusAt(run) }}</span>
                <span v-if="run.created_by?.name" class="truncate text-right font-medium text-slate-500">
                  {{ run.created_by.name }}
                </span>
              </div>
              <div class="mt-1.5 truncate font-mono text-[10px] text-slate-300">
                <template v-if="run.number_formatted">#{{ run.number_formatted }}</template>
                <template v-else>{{ run.id }}</template>
              </div>
            </button>
          </div>
        </div>

        <!-- Pagination -->
        <div v-if="runsTotal > 0" class="shrink-0 border-t border-slate-200 bg-white px-4 py-3">
          <div class="mb-2 text-center text-[11px] text-slate-400">
            {{ runsFrom ?? 0 }}–{{ runsTo ?? 0 }} из {{ runsTotal }}
          </div>
          <div class="flex items-center justify-center gap-1">
            <button
                class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 disabled:opacity-30"
                :disabled="runsPage === 1 || runsLoading"
                @click="prevPage"
            >
              <ChevronLeft :size="15"/>
            </button>

            <template v-if="runPageNumbers[0] > 1">
              <button
                  class="inline-flex h-7 min-w-[28px] items-center justify-center rounded-lg px-1 text-[12px] text-slate-600 hover:bg-slate-100"
                  @click="goToPage(1)">1
              </button>
              <span v-if="runPageNumbers[0] > 2" class="px-0.5 text-[12px] text-slate-300">…</span>
            </template>

            <button
                v-for="page in runPageNumbers"
                :key="page"
                class="inline-flex h-7 min-w-[28px] items-center justify-center rounded-lg px-1 text-[12px] transition"
                :class="page === runsPage ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100'"
                :disabled="runsLoading"
                @click="goToPage(page)"
            >
              {{ page }}
            </button>

            <template v-if="runPageNumbers[runPageNumbers.length - 1] < totalPages">
              <span v-if="runPageNumbers[runPageNumbers.length - 1] < totalPages - 1"
                    class="px-0.5 text-[12px] text-slate-300">…</span>
              <button
                  class="inline-flex h-7 min-w-[28px] items-center justify-center rounded-lg px-1 text-[12px] text-slate-600 hover:bg-slate-100"
                  @click="goToPage(totalPages)">{{ totalPages }}
              </button>
            </template>

            <button
                class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 disabled:opacity-30"
                :disabled="runsPage === totalPages || runsLoading"
                @click="nextPage"
            >
              <ChevronRight :size="15"/>
            </button>
          </div>
        </div>
      </aside>

      <!-- ══ Right panel ═══════════════════════════════════════════════ -->
      <div class="flex min-h-0 flex-1 flex-col overflow-hidden bg-slate-50">
        <!-- Empty state -->
        <div v-if="!hasSelection" class="flex flex-1 flex-col items-center justify-center gap-4 text-center">
          <div
              class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white shadow-[0_1px_3px_rgba(15,23,42,0.08),0_0_0_1px_rgba(15,23,42,0.06)] text-slate-400">
            <ClipboardList :size="24"/>
          </div>
          <div>
            <div class="text-[15px] font-semibold text-slate-900">Выберите сессию</div>
            <div class="mt-1 text-[13px] text-slate-500">или запустите сценарий из каталога</div>
          </div>
          <Link
              :href="route('workspace.scenarios')"
              class="inline-flex h-9 items-center gap-2 rounded-xl bg-blue-600 px-5 text-[13px] font-medium text-white shadow-sm transition hover:bg-blue-700"
          >
            Перейти к сценариям
          </Link>
        </div>

        <!-- Player -->
        <template v-else>
          <!-- Session header -->
          <div class="shrink-0 border-b border-slate-200 bg-white px-6 py-4">
            <div class="flex items-start gap-4">
              <div class="min-w-0">
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
                  <span class="font-mono">
                    <template v-if="activeRun.number_formatted">#{{ activeRun.number_formatted }}</template>
                    <template v-else>{{ activeRun.id }}</template>
                  </span>
                  <template v-if="activeRun.created_at">
                    <span class="text-slate-300">·</span>
                    <span>{{ formatDateTime(activeRun.created_at) }}</span>
                  </template>
                </div>
              </div>
            </div>
          </div>

          <!-- Player area -->
          <div class="min-h-0 flex-1 overflow-y-auto p-6">
            <div class="mx-auto max-w-2xl">
              <ScenarioPlayer
                  :key="playerKey"
                  :scenario-id="selectedScenarioId ?? undefined"
                  :scenario-version-id="selectedScenarioVersionId ?? undefined"
                  :run-id="selectedRunId ?? undefined"
                  @update:run="onRunUpdate"
              />
            </div>
          </div>
        </template>
      </div>
    </div>
  </AppShell>
</template>
