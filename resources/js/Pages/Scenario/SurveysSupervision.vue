<script setup lang="ts">
import AppShell from '@/layouts/AppShell.vue'
import PageHeader from '@/components/PageHeader.vue'
import SearchInput from '@/components/SearchInput.vue'
import DatePickerFilter from '@/components/ui/date-picker/DatePickerFilter.vue'
import SupervisionStatsCards from './supervision/SupervisionStatsCards.vue'
import SupervisionRunsTable from './supervision/SupervisionRunsTable.vue'
import {useDashboardNavigation} from '@/composables/useDashboardNavigation'
import {scenarioRepository} from '@/modules/scenario/repositories/scenarioRepository'
import {scenarioRunRepository} from '@/modules/scenario/repositories/scenarioRunRepository'
import type {Scenario, ScenarioActor, ScenarioRunListItem} from '@/modules/scenario/types/scenario'
import {Head} from '@inertiajs/vue3'
import {ChevronDown, ClipboardList, X} from 'lucide-vue-next'
import {computed, onMounted, ref, watch} from 'vue'

const {navigationItems} = useDashboardNavigation()

const runs = ref<ScenarioRunListItem[]>([])
const runsTotal = ref(0)
const runsPage = ref(1)
const runsLastPage = ref(1)
const runsLoading = ref(false)

const statsTotal = ref<number | null>(null)
const statsActive = ref<number | null>(null)
const statsCompleted = ref<number | null>(null)
const statsFailed = ref<number | null>(null)
const statsLoading = ref(false)

const statusFilter = ref('')
const search = ref('')
const searchCommitted = ref('')
const dateFrom = ref('')
const dateTo = ref('')
const selectedUsers = ref<ScenarioActor[]>([])
const selectedScenario = ref<Scenario | null>(null)

const userDropdownOpen = ref(false)
const userSearchQuery = ref('')
const userSearchResults = ref<ScenarioActor[]>([])
const userSearchLoading = ref(false)
let userSearchTimer: ReturnType<typeof setTimeout> | null = null

const scenarioDropdownOpen = ref(false)
const scenarioSearchQuery = ref('')
const scenarioSearchResults = ref<Scenario[]>([])
const scenarioSearchLoading = ref(false)
let scenarioSearchTimer: ReturnType<typeof setTimeout> | null = null

let searchTimer: ReturnType<typeof setTimeout> | null = null

const STAT_VALUES = computed(() => ({
  total: statsTotal.value,
  active: statsActive.value,
  completed: statsCompleted.value,
  failed: statsFailed.value,
}))

const hasActiveFilters = computed(() =>
    !!searchCommitted.value || !!dateFrom.value || !!dateTo.value ||
    selectedUsers.value.length > 0 || selectedScenario.value !== null,
)

const filteredUserResults = computed(() =>
    userSearchResults.value.filter(u => !selectedUsers.value.some(s => s.id === u.id)),
)

const userBtnLabel = computed(() => {
  if (!selectedUsers.value.length) return 'Пользователь'
  const first = selectedUsers.value[0]
  const name = first.name ?? first.login ?? `#${first.id}`
  return selectedUsers.value.length === 1 ? name : `${name} +${selectedUsers.value.length - 1}`
})

const scenarioBtnLabel = computed(() => selectedScenario.value?.name ?? 'Сценарий')

function closeScenarioDropdown() {
  setTimeout(() => {
    scenarioDropdownOpen.value = false
    scenarioSearchQuery.value = ''
    scenarioSearchResults.value = []
  }, 150)
}

function closeUserDropdown() {
  setTimeout(() => {
    userDropdownOpen.value = false
    userSearchQuery.value = ''
    userSearchResults.value = []
  }, 150)
}

function buildQs(includePerPage = true): URLSearchParams {
  const qs = new URLSearchParams()
  if (runsPage.value > 1) qs.set('page[number]', String(runsPage.value))
  if (includePerPage) qs.set('page[size]', '20')
  if (searchCommitted.value.trim()) qs.set('filter[search]', searchCommitted.value.trim())
  if (statusFilter.value) qs.set('filter[status]', statusFilter.value)
  if (dateFrom.value) qs.set('filter[created_from]', dateFrom.value)
  if (dateTo.value) qs.set('filter[created_to]', dateTo.value)
  if (selectedScenario.value) qs.set('filter[scenario_id]', selectedScenario.value.id)
  selectedUsers.value.forEach(u => qs.append('filter[created_by][]', String(u.id)))
  return qs
}

function syncUrl(): void {
  const qs = buildQs(false).toString()
  const next = qs ? `${window.location.pathname}?${qs}` : window.location.pathname
  if (next !== window.location.pathname + window.location.search) {
    window.history.replaceState(null, '', next)
  }
}

async function loadRuns() {
  runsLoading.value = true
  statsLoading.value = true
  try {
    const page = await scenarioRunRepository.list(buildQs())
    runs.value = page.data
    runsTotal.value = page.meta.total
    runsLastPage.value = page.meta.last_page

    statsTotal.value = page.meta.stats?.total ?? 0
    statsActive.value = page.meta.stats?.active ?? 0
    statsCompleted.value = page.meta.stats?.completed ?? 0
    statsFailed.value = page.meta.stats?.failed ?? 0

    syncUrl()
  } catch (e) {
    console.error('Failed to load runs', e)
  } finally {
    runsLoading.value = false
    statsLoading.value = false
  }
}

let isInitializing = true

function reload() {
  if (isInitializing) return
  runsPage.value = 1
  loadRuns()
}

function applySearch() {
  searchCommitted.value = search.value;
  reload()
}

function onSearchInput() {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(applySearch, 400)
}

function pickStatus(value: string) {
  statusFilter.value = statusFilter.value === value ? '' : value
  reload()
}

async function searchUsers(q: string) {
  userSearchLoading.value = true
  try {
    userSearchResults.value = await scenarioRunRepository.users(q)
  } catch {
    userSearchResults.value = []
  } finally {
    userSearchLoading.value = false
  }
}

function addUser(u: ScenarioActor) {
  if (!selectedUsers.value.some(s => s.id === u.id)) {
    selectedUsers.value.push(u);
    reload()
  }
  userSearchQuery.value = '';
  userSearchResults.value = [];
  userDropdownOpen.value = false
}

watch(userSearchQuery, val => {
  if (userSearchTimer) clearTimeout(userSearchTimer)
  if (!val.trim()) {
    userSearchResults.value = [];
    return
  }
  userSearchTimer = setTimeout(() => searchUsers(val), 300)
})

async function searchScenarios(q: string) {
  scenarioSearchLoading.value = true
  try {
    const qs = new URLSearchParams({'filter[search]': q, 'filter[active_only]': '0', 'page[size]': '10'})
    scenarioSearchResults.value = (await scenarioRepository.list(qs)).data
  } catch {
    scenarioSearchResults.value = []
  } finally {
    scenarioSearchLoading.value = false
  }
}

function pickScenario(s: Scenario) {
  selectedScenario.value = s
  scenarioDropdownOpen.value = false
  scenarioSearchQuery.value = ''
  scenarioSearchResults.value = []
  reload()
}

function clearScenario() {
  selectedScenario.value = null;
  reload()
}

watch(scenarioSearchQuery, val => {
  if (scenarioSearchTimer) clearTimeout(scenarioSearchTimer)
  if (!val.trim()) {
    scenarioSearchResults.value = [];
    return
  }
  scenarioSearchTimer = setTimeout(() => searchScenarios(val), 300)
})

watch(dateFrom, reload)
watch(dateTo, reload)

function resetFilters() {
  search.value = '';
  searchCommitted.value = ''
  dateFrom.value = '';
  dateTo.value = ''
  selectedUsers.value = [];
  selectedScenario.value = null
  reload()
}

const AVATAR_COLORS = ['#2563EB', '#059669', '#7C3AED', '#DC2626', '#D97706', '#0891B2']

function initials(name: string): string {
  return name.split(' ').slice(0, 2).map(s => s[0] ?? '').join('').toUpperCase() || '?'
}

function avatarColor(name: string): string {
  let h = 0
  for (let i = 0; i < name.length; i++) h = (h * 31 + name.charCodeAt(i)) % AVATAR_COLORS.length
  return AVATAR_COLORS[Math.abs(h)]
}

const copiedVersionId = ref<string | null>(null)

async function copyVersionId(id: string) {
  try {
    await navigator.clipboard.writeText(id)
    copiedVersionId.value = id
    setTimeout(() => {
      copiedVersionId.value = null
    }, 1500)
  } catch { /* ignore */
  }
}

async function initFromUrl(): Promise<void> {
  const qs = new URLSearchParams(window.location.search)

  const page = qs.get('page[number]')
  if (page) runsPage.value = Math.max(1, Number(page) || 1)

  const s = qs.get('filter[search]')
  if (s) {
    search.value = s;
    searchCommitted.value = s
  }

  const status = qs.get('filter[status]')
  if (status) statusFilter.value = status

  const cf = qs.get('filter[created_from]')
  if (cf) dateFrom.value = cf

  const ct = qs.get('filter[created_to]')
  if (ct) dateTo.value = ct

  const scenarioId = qs.get('filter[scenario_id]')
  if (scenarioId) {
    try {
      selectedScenario.value = await scenarioRepository.find(scenarioId)
    } catch { /* удалён — игнорируем */
    }
  }

  const userIds = qs.getAll('filter[created_by][]')
  if (userIds.length) {
    try {
      selectedUsers.value = await scenarioRunRepository.usersByIds(userIds)
    } catch { /* игнорируем */
    }
  }
}

onMounted(async () => {
  try {
    await initFromUrl()
  } catch (e) {
    console.error('Failed to init from URL', e)
  } finally {
    isInitializing = false
  }
  await loadRuns()
})
</script>

<template>
  <Head title="Опросы"/>

  <AppShell title="Опросы" :navigation-items="navigationItems">
    <div class="min-h-full bg-slate-50">
      <div class="mx-auto max-w-6xl px-6 py-8">

        <PageHeader title="Опросы" subtitle="Мониторинг и управление всеми запущенными сценариями."/>

        <SupervisionStatsCards
            :active-status="statusFilter"
            :stat-values="STAT_VALUES"
            :loading="statsLoading"
            @pick="pickStatus"
        />

        <!-- Filters + search row -->
        <div class="mb-3 flex items-center justify-between gap-4">
          <div class="flex flex-wrap items-center gap-2">

            <!-- Scenario dropdown -->
            <div class="relative">
              <button
                  type="button"
                  class="inline-flex h-8 items-center gap-1.5 rounded-lg border px-3 text-[12px] font-medium transition"
                  :class="selectedScenario
                ? 'border-blue-300 bg-blue-50 text-blue-700'
                : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                  @click="scenarioDropdownOpen = !scenarioDropdownOpen; userDropdownOpen = false"
              >
                <ClipboardList :size="12"/>
                <span class="max-w-[140px] truncate">{{ scenarioBtnLabel }}</span>
                <X v-if="selectedScenario" :size="11" class="shrink-0" @click.stop="clearScenario"/>
                <ChevronDown v-else :size="12" class="shrink-0 text-slate-400"/>
              </button>
              <div
                  v-if="scenarioDropdownOpen"
                  class="absolute left-0 top-full z-20 mt-1 w-80 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg"
              >
                <div class="border-b border-slate-100 px-3 py-2">
                  <input
                      v-model="scenarioSearchQuery"
                      type="text"
                      class="h-7 w-full rounded-lg bg-slate-50 px-2.5 text-[12px] outline-none placeholder:text-slate-400 focus:ring-2 focus:ring-blue-100"
                      placeholder="Поиск сценария..."
                      @blur="closeScenarioDropdown"
                  >
                </div>
                <div v-if="scenarioSearchLoading" class="px-3 py-3 text-center text-[12px] text-slate-400">Загрузка…
                </div>
                <div v-else-if="scenarioSearchResults.length" class="max-h-52 overflow-y-auto py-1">
                  <button
                      v-for="s in scenarioSearchResults"
                      :key="s.id"
                      type="button"
                      class="flex w-full items-center gap-2 px-3 py-2 text-left text-[12px] text-slate-800 transition hover:bg-blue-50"
                      @mousedown.prevent="pickScenario(s)"
                  >
                    <ClipboardList :size="11" class="shrink-0 text-slate-400"/>
                    {{ s.name }}
                  </button>
                </div>
                <div v-else class="px-3 py-3 text-center text-[12px] text-slate-400">
                  {{ scenarioSearchQuery ? 'Не найдено' : 'Введите название сценария' }}
                </div>
              </div>
            </div>

            <!-- User dropdown -->
            <div class="relative">
              <button
                  type="button"
                  class="inline-flex h-8 items-center gap-1.5 rounded-lg border px-3 text-[12px] font-medium transition"
                  :class="selectedUsers.length
                ? 'border-blue-300 bg-blue-50 text-blue-700'
                : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                  @click="userDropdownOpen = !userDropdownOpen; scenarioDropdownOpen = false"
              >
                <span class="max-w-[140px] truncate">{{ userBtnLabel }}</span>
                <span v-if="selectedUsers.length"
                      class="flex h-4 w-4 items-center justify-center rounded-full bg-blue-600 text-[10px] font-bold text-white"
                >{{ selectedUsers.length }}</span>
                <ChevronDown :size="12" class="shrink-0 text-slate-400"/>
              </button>
              <div
                  v-if="userDropdownOpen"
                  class="absolute left-0 top-full z-20 mt-1 w-72 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg"
              >
                <div class="border-b border-slate-100 px-3 py-2">
                  <input
                      v-model="userSearchQuery"
                      type="text"
                      class="h-7 w-full rounded-lg bg-slate-50 px-2.5 text-[12px] outline-none placeholder:text-slate-400 focus:ring-2 focus:ring-blue-100"
                      placeholder="Поиск пользователя..."
                      @blur="closeUserDropdown"
                  >
                </div>
                <div v-if="userSearchLoading" class="px-3 py-3 text-center text-[12px] text-slate-400">Загрузка…</div>
                <div v-else-if="filteredUserResults.length" class="max-h-52 overflow-y-auto py-1">
                  <button
                      v-for="u in filteredUserResults"
                      :key="u.id"
                      type="button"
                      class="flex w-full items-center gap-2 px-3 py-2 text-left text-[12px] text-slate-800 transition hover:bg-blue-50"
                      @mousedown.prevent="addUser(u)"
                  >
                  <span
                      class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[9px] font-bold text-white"
                      :style="{ background: avatarColor(u.name ?? u.login ?? '') }"
                  >{{ initials(u.name ?? u.login ?? '') }}</span>
                    <span class="truncate">{{ u.name ?? u.login }}</span>
                    <span v-if="u.fio" class="ml-auto shrink-0 text-[11px] text-slate-400">{{ u.fio }}</span>
                  </button>
                </div>
                <div v-else class="px-3 py-3 text-center text-[12px] text-slate-400">
                  {{ userSearchQuery ? 'Не найдено' : 'Введите имя пользователя' }}
                </div>
              </div>
            </div>

            <!-- Date range -->
            <div class="flex items-center gap-1.5">
              <span class="text-[12px] text-slate-400 select-none">с</span>
              <div class="w-36">
                <DatePickerFilter v-model="dateFrom"/>
              </div>
            </div>
            <div class="flex items-center gap-1.5">
              <span class="text-[12px] text-slate-400 select-none">по</span>
              <div class="w-36">
                <DatePickerFilter v-model="dateTo"/>
              </div>
            </div>

            <!-- Reset link (выбранные сценарий/пользователи уже отображены в самих дропдаунах) -->
            <button
                v-if="hasActiveFilters"
                type="button"
                class="text-[12px] text-slate-400 underline hover:text-slate-600"
                @click="resetFilters"
            >
              Сбросить
            </button>
          </div>

          <!-- Search (right side, like Users page) -->
          <div class="w-56">
            <SearchInput
                v-model="search"
                placeholder="Сценарий или ID..."
                @update:model-value="onSearchInput"
            />
          </div>
        </div>

        <SupervisionRunsTable
            :runs="runs"
            :loading="runsLoading"
            :current-page="runsPage"
            :total-pages="runsLastPage"
            :total="runsTotal"
            :copied-version-id="copiedVersionId"
            @update:current-page="runsPage = $event; loadRuns()"
            @copy-version="copyVersionId"
        />
      </div>
    </div>
  </AppShell>
</template>
