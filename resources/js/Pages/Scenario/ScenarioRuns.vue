<script setup>
import AppShell from '@/layouts/AppShell.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover'
import { Skeleton } from '@/components/ui/skeleton'
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table'
import PageContent from '@/components/PageContent.vue'
import { useDashboardNavigation } from '@/composables/useDashboardNavigation'
import { getJson } from '@/lib/http'
import { formatDateTime } from '@/lib/formatters'
import { Head, Link } from '@inertiajs/vue3'
import { ChevronDown, Search, X } from 'lucide-vue-next'
import { computed, onMounted, ref, watch } from 'vue'

const { navigationItems } = useDashboardNavigation()

const loading      = ref(false)
const loadError    = ref('')
const runs         = ref([])
const total        = ref(0)
const searchInput  = ref('')
const activeSearch = ref('')
const statusFilter = ref('active')
const selectedUsers = ref([])

const userPopoverOpen    = ref(false)
const userSearchQuery    = ref('')
const userSearchResults  = ref([])
const userSearchLoading  = ref(false)
let userSearchTimer = null

const STATUS_LABELS = {
    completed: 'Завершён',
    failed: 'Ошибка',
    active: 'В процессе',
}

const STATUS_VARIANTS = {
    completed: 'default',
    failed: 'destructive',
    active: 'secondary',
}

const FILTER_OPTIONS = [
    { value: '', label: 'Все' },
    { value: 'active', label: 'В процессе' },
    { value: 'completed', label: 'Завершённые' },
    { value: 'failed', label: 'С ошибкой' },
]

const hasActiveFilters = computed(() => selectedUsers.value.length > 0 || !!activeSearch.value)

const filteredUserResults = computed(() =>
    userSearchResults.value.filter((u) => !selectedUsers.value.some((s) => s.id === u.id)),
)

const userTriggerLabel = computed(() => {
    if (!selectedUsers.value.length) return 'Пользователь'
    if (selectedUsers.value.length === 1) return selectedUsers.value[0].name
    return `${selectedUsers.value[0].name} +${selectedUsers.value.length - 1}`
})

async function loadRuns() {
    loading.value   = true
    loadError.value = ''
    try {
        const params = new URLSearchParams()
        if (statusFilter.value) params.set('status', statusFilter.value)
        if (activeSearch.value) params.set('search', activeSearch.value)
        selectedUsers.value.forEach((u) => params.append('created_by[]', u.id))
        const url = `/api/scenarios/runner${params.toString() ? '?' + params.toString() : ''}`
        const payload = await getJson(url, 'Не удалось загрузить список сессий.')
        runs.value  = payload.runs ?? []
        total.value = payload.total ?? 0
    } catch (error) {
        loadError.value = error.message
    } finally {
        loading.value = false
    }
}

function setStatus(value) { statusFilter.value = value; loadRuns() }
function applySearch() { activeSearch.value = searchInput.value.trim(); loadRuns() }
function clearSearch()  { searchInput.value = ''; activeSearch.value = ''; loadRuns() }

function resetAllFilters() {
    statusFilter.value  = ''
    selectedUsers.value = []
    searchInput.value   = ''
    activeSearch.value  = ''
    loadRuns()
}

function addUser(user) {
    if (!selectedUsers.value.some((u) => u.id === user.id)) {
        selectedUsers.value.push(user)
        loadRuns()
    }
    userSearchQuery.value   = ''
    userSearchResults.value = []
}

function removeUser(userId) {
    selectedUsers.value = selectedUsers.value.filter((u) => u.id !== userId)
    loadRuns()
}

async function searchUsers(query) {
    if (!query.trim()) { userSearchResults.value = []; return }
    userSearchLoading.value = true
    try {
        const payload = await getJson(`/api/scenarios/runner/users?search=${encodeURIComponent(query)}`, '')
        userSearchResults.value = payload.users ?? []
    } catch {
        userSearchResults.value = []
    } finally {
        userSearchLoading.value = false
    }
}

watch(userSearchQuery, (val) => {
    clearTimeout(userSearchTimer)
    if (!val.trim()) { userSearchResults.value = []; return }
    userSearchTimer = setTimeout(() => searchUsers(val), 300)
})

watch(userPopoverOpen, (open) => {
    if (!open) { userSearchQuery.value = ''; userSearchResults.value = [] }
})

function completedAt(run) {
    return (run.status === 'completed' || run.status === 'failed')
        ? formatDateTime(run.updated_at)
        : '—'
}

onMounted(loadRuns)
</script>

<template>
    <Head title="Сессии опросов" />

    <AppShell
        title="Сессии опросов"
        description="Список всех запущенных сессий: завершённые и незавершённые опросы."
        :navigation-items="navigationItems"
        flush
    >
        <PageContent>
            <template #bars>
            <!-- Search + filters bar -->
            <div class="flex h-11 shrink-0 items-center gap-2 border-b border-slate-200 bg-white px-3">
                <div class="relative">
                    <Search class="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-slate-400" />
                    <Input
                        v-model="searchInput"
                        placeholder="Сценарий или ID сессии..."
                        class="h-8 w-64 pl-8 text-xs"
                        :class="searchInput ? 'pr-8' : ''"
                        @keydown.enter="applySearch"
                    />
                    <button
                        v-if="searchInput"
                        type="button"
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700"
                        @click="clearSearch"
                    >
                        <X class="size-3.5" />
                    </button>
                </div>

                <Button size="sm" class="h-8 px-3 text-xs" @click="applySearch">Найти</Button>

                <Popover v-model:open="userPopoverOpen">
                    <PopoverTrigger as-child>
                        <button
                            type="button"
                            class="flex h-8 w-44 items-center gap-1.5 rounded-lg border px-2.5 text-xs transition"
                            :class="selectedUsers.length
                                ? 'border-cyan-300 bg-cyan-50 text-slate-900'
                                : 'border-slate-200 bg-white text-slate-500 hover:border-slate-300'"
                        >
                            <span class="flex-1 truncate text-left">{{ userTriggerLabel }}</span>
                            <span v-if="selectedUsers.length" class="flex size-4 shrink-0 items-center justify-center rounded-full bg-cyan-500 text-[9px] font-semibold text-white">
                                {{ selectedUsers.length }}
                            </span>
                            <ChevronDown class="size-3 shrink-0 text-slate-400" />
                        </button>
                    </PopoverTrigger>
                    <PopoverContent align="start" class="w-72 p-0">
                        <div v-if="selectedUsers.length" class="flex flex-wrap gap-1.5 border-b border-slate-100 p-2">
                            <span
                                v-for="u in selectedUsers"
                                :key="u.id"
                                class="inline-flex items-center gap-1 rounded-md bg-slate-100 py-0.5 pl-2 pr-1 text-xs font-medium text-slate-700"
                            >
                                {{ u.name }}
                                <button type="button" class="text-slate-400 hover:text-slate-700" @click="removeUser(u.id)">
                                    <X class="size-3" />
                                </button>
                            </span>
                        </div>
                        <div class="border-b border-slate-100 p-2">
                            <div class="relative">
                                <Search class="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-slate-400" />
                                <input
                                    v-model="userSearchQuery"
                                    placeholder="Поиск..."
                                    class="w-full rounded-lg bg-transparent py-1.5 pl-8 pr-2 text-sm outline-none placeholder:text-slate-400"
                                />
                            </div>
                        </div>
                        <div class="max-h-52 overflow-y-auto p-1">
                            <div v-if="userSearchLoading" class="space-y-1 p-1">
                                <Skeleton class="h-8 w-full" />
                                <Skeleton class="h-8 w-full" />
                            </div>
                            <template v-else-if="filteredUserResults.length">
                                <button
                                    v-for="user in filteredUserResults"
                                    :key="user.id"
                                    type="button"
                                    class="flex w-full items-center gap-2.5 rounded-lg px-2 py-1.5 text-sm hover:bg-slate-50"
                                    @click="addUser(user)"
                                >
                                    <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-700">
                                        {{ user.name.charAt(0).toUpperCase() }}
                                    </span>
                                    <span class="flex min-w-0 flex-col text-left">
                                        <span class="truncate text-slate-800">{{ user.name }}</span>
                                        <span v-if="user.fio" class="truncate text-xs text-slate-400">{{ user.fio }}</span>
                                    </span>
                                </button>
                            </template>
                            <p v-else class="py-6 text-center text-sm text-slate-400">
                                {{ userSearchQuery ? 'Не найдено' : 'Начните вводить имя' }}
                            </p>
                        </div>
                    </PopoverContent>
                </Popover>

                <div class="flex-1" />

                <span class="text-xs text-slate-400">{{ loading ? '' : `Найдено: ${total}` }}</span>

                <button
                    v-if="hasActiveFilters"
                    type="button"
                    class="flex h-8 items-center gap-1.5 rounded-lg px-2 text-xs text-slate-500 hover:bg-slate-100"
                    @click="resetAllFilters"
                >
                    <X class="size-3.5" />
                    Сбросить
                </button>
            </div>

            <!-- Status tab bar -->
            <div class="flex h-10 shrink-0 items-center gap-1 border-b border-slate-200 bg-white px-3">
                <button
                    v-for="opt in FILTER_OPTIONS"
                    :key="opt.value"
                    type="button"
                    class="h-full border-b-2 px-3 text-sm font-medium transition"
                    :class="statusFilter === opt.value
                        ? 'border-cyan-500 text-slate-900'
                        : 'border-transparent text-slate-500 hover:text-slate-700'"
                    @click="setStatus(opt.value)"
                >
                    {{ opt.label }}
                </button>
            </div>
            </template>

            <div v-if="loadError" class="flex h-full items-center justify-center p-8">
                <div class="space-y-1 text-center">
                    <div class="text-sm font-semibold text-slate-900">Ошибка загрузки</div>
                    <div class="text-sm text-slate-500">{{ loadError }}</div>
                </div>
            </div>

            <div v-else-if="loading" class="space-y-2 p-4">
                <Skeleton v-for="i in 8" :key="i" class="h-12 w-full rounded-xl" />
            </div>

            <Table v-else>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Сценарий</TableHead>
                            <TableHead>Статус</TableHead>
                            <TableHead class="hidden md:table-cell">Завершена</TableHead>
                            <TableHead class="hidden lg:table-cell">Создал</TableHead>
                            <TableHead class="text-right">Действие</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="run in runs" :key="run.id">
                            <TableCell>
                                <div class="text-sm font-medium text-slate-900">
                                    {{ run.scenario_name ?? `Сценарий #${run.scenario_id}` }}
                                </div>
                                <div class="font-mono text-[10px] text-slate-400">{{ run.id }}</div>
                            </TableCell>
                            <TableCell>
                                <Badge :variant="STATUS_VARIANTS[run.status] ?? 'secondary'" class="text-[10px]">
                                    {{ STATUS_LABELS[run.status] ?? run.status }}
                                </Badge>
                            </TableCell>
                            <TableCell class="hidden text-sm text-slate-500 md:table-cell">
                                {{ completedAt(run) }}
                            </TableCell>
                            <TableCell class="hidden lg:table-cell">
                                <div class="text-sm text-slate-700">
                                    {{ run.created_by ? (run.created_by.fio || run.created_by.login || run.created_by.name) : '—' }}
                                </div>
                                <div class="text-xs text-slate-400">{{ formatDateTime(run.created_at) }}</div>
                            </TableCell>
                            <TableCell class="text-right">
                                <Button as-child size="sm" :variant="run.status === 'active' ? 'default' : 'outline'" class="h-8">
                                    <Link :href="route('scenario-runs.play', { run: run.id })">
                                        {{ run.status === 'active' ? 'Продолжить' : 'Просмотр' }}
                                    </Link>
                                </Button>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="!runs.length">
                            <TableCell colspan="5" class="h-24 text-center text-sm text-slate-400">
                                Сессий не найдено. Попробуйте изменить фильтры.
                            </TableCell>
                        </TableRow>
                    </TableBody>
            </Table>
</PageContent>
    </AppShell>
</template>
