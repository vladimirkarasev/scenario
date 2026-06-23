<script setup lang="ts">
import AppShell from '@/layouts/AppShell.vue'
import EmptyState from '@/components/EmptyState.vue'
import ListPagination from '@/components/ListPagination.vue'
import PageHeader from '@/components/PageHeader.vue'
import SearchInput from '@/components/SearchInput.vue'
import { Button } from '@/components/ui/button'
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table'
import { useDashboardNavigation } from '@/composables/useDashboardNavigation'
import { useWebhookRequestFilters } from '@/modules/proxy/composables/useWebhookRequestFilters'
import { useWebhookRequestList } from '@/modules/proxy/composables/useWebhookRequestList'
import { Head, router } from '@inertiajs/vue3'
import { Activity, ChevronDown, Loader2, Plug, RefreshCw, X, Zap } from 'lucide-vue-next'

const { navigationItems } = useDashboardNavigation()

const {
    params, loading, items, meta, search, page, load,
} = useWebhookRequestList()

const {
    STATUS_OPTIONS,
    endpointOpen, statusOpen, endpointSearch, endpointResults,
    selectedEndpoint, selectedStatusValue, selectedStatusLabel, hasFilters,
    onEndpointInput, selectEndpoint, clearEndpoint, selectStatus, clearStatus, clear,
    closeEndpointSoon, closeStatusSoon,
} = useWebhookRequestFilters(params)

const STATUS_CLASS: Record<string, string> = {
    received:        'bg-slate-100 text-slate-600',
    accepted:        'bg-blue-50 text-blue-700',
    rejected:        'bg-amber-50 text-amber-700',
    failed:          'bg-red-50 text-red-700',
    processed:       'bg-emerald-50 text-emerald-700',
}

function shortId(id: string) {
    return id.slice(0, 8) + '…'
}

function fmt(iso: string | null) {
    if (!iso) return '—'
    return new Date(iso).toLocaleString('ru-RU', { dateStyle: 'short', timeStyle: 'short' })
}
</script>

<template>
    <Head title="Webhook запросы" />

    <AppShell title="Webhook запросы" :navigation-items="navigationItems">
        <div class="min-h-full bg-slate-50">
            <div class="mx-auto max-w-6xl px-6 py-8">
                <PageHeader
                    title="Webhook запросы"
                    subtitle="История входящих запросов."
                />

                <!-- Search + refresh -->
                <div class="mb-3 flex items-center gap-3">
                    <div class="flex-1">
                        <SearchInput v-model="search" placeholder="Поиск по request ID или эндпоинту…" />
                    </div>
                    <Button variant="outline" class="gap-2" :disabled="loading" @click="load">
                        <Loader2 v-if="loading" :size="14" class="animate-spin" />
                        <RefreshCw v-else :size="14" />
                        Обновить
                    </Button>
                </div>

                <!-- Filters -->
                <div class="mb-4 flex flex-wrap items-center gap-2">
                    <!-- Endpoint filter -->
                    <div class="relative">
                        <button
                            class="inline-flex h-8 items-center gap-1.5 rounded-lg border px-3 text-[12px] font-medium transition"
                            :class="selectedEndpoint ? 'border-blue-300 bg-blue-50 text-blue-700' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                            @click="endpointOpen = !endpointOpen; statusOpen = false"
                        >
                            <Plug :size="12" />
                            Эндпоинт
                            <ChevronDown :size="12" class="text-slate-400" />
                        </button>
                        <div
                            v-if="endpointOpen"
                            class="absolute left-0 top-full z-20 mt-1 w-72 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg"
                        >
                            <div class="border-b border-slate-100 px-3 py-2">
                                <input
                                    v-model="endpointSearch"
                                    type="text"
                                    class="h-7 w-full rounded-lg bg-slate-50 px-2.5 text-[12px] outline-none placeholder:text-slate-400 focus:ring-2 focus:ring-blue-100"
                                    placeholder="Поиск эндпоинта..."
                                    @input="onEndpointInput"
                                    @blur="closeEndpointSoon"
                                />
                            </div>
                            <div v-if="endpointResults.length" class="max-h-56 overflow-y-auto py-1">
                                <button
                                    v-for="e in endpointResults"
                                    :key="e.id"
                                    type="button"
                                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-[12px] text-slate-800 transition hover:bg-blue-50"
                                    @mousedown.prevent="selectEndpoint(e)"
                                >
                                    <Plug :size="11" class="shrink-0 text-slate-400" />
                                    <span class="truncate">{{ e.name }}</span>
                                </button>
                            </div>
                            <div v-else class="px-3 py-3 text-center text-[12px] text-slate-400">
                                {{ endpointSearch.trim() ? 'Не найдено' : 'Введите название эндпоинта' }}
                            </div>
                        </div>
                    </div>

                    <!-- Status filter -->
                    <div class="relative">
                        <button
                            class="inline-flex h-8 items-center gap-1.5 rounded-lg border px-3 text-[12px] font-medium transition"
                            :class="selectedStatusValue ? 'border-blue-300 bg-blue-50 text-blue-700' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                            @click="statusOpen = !statusOpen; endpointOpen = false"
                            @blur="closeStatusSoon"
                        >
                            <Activity :size="12" />
                            Статус
                            <ChevronDown :size="12" class="text-slate-400" />
                        </button>
                        <div
                            v-if="statusOpen"
                            class="absolute left-0 top-full z-20 mt-1 w-44 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg"
                        >
                            <button
                                v-for="s in STATUS_OPTIONS"
                                :key="s.value"
                                type="button"
                                class="flex w-full items-center gap-2 px-3 py-2 text-left text-[12px] text-slate-800 transition hover:bg-blue-50"
                                :class="selectedStatusValue === s.value ? 'font-semibold text-blue-700' : ''"
                                @mousedown.prevent="selectStatus(s.value)"
                            >
                                {{ s.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Active filter chips -->
                    <template v-if="hasFilters">
                        <span
                            v-if="selectedEndpoint"
                            class="inline-flex h-7 items-center gap-1 rounded-full bg-blue-100 pl-2.5 pr-1.5 text-[12px] font-medium text-blue-700"
                        >
                            <Plug :size="11" />
                            {{ selectedEndpoint.name }}
                            <button type="button" class="ml-0.5 rounded-full p-0.5 hover:bg-blue-200" @click="clearEndpoint"><X :size="10" /></button>
                        </span>
                        <span
                            v-if="selectedStatusLabel"
                            class="inline-flex h-7 items-center gap-1 rounded-full bg-blue-100 pl-2.5 pr-1.5 text-[12px] font-medium text-blue-700"
                        >
                            <Activity :size="11" />
                            {{ selectedStatusLabel }}
                            <button type="button" class="ml-0.5 rounded-full p-0.5 hover:bg-blue-200" @click="clearStatus"><X :size="10" /></button>
                        </span>
                        <button
                            type="button"
                            class="text-[12px] text-slate-400 underline hover:text-slate-600"
                            @click="clear"
                        >
                            Сбросить
                        </button>
                    </template>
                </div>

                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead class="w-[180px]">Request ID</TableHead>
                                <TableHead>Эндпоинт</TableHead>
                                <TableHead class="w-[140px]">Статус</TableHead>
                                <TableHead class="w-[140px]">IP</TableHead>
                                <TableHead class="w-[150px]">Получен</TableHead>
                                <TableHead>Ошибка</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <template v-if="loading">
                                <TableRow v-for="i in 5" :key="i">
                                    <TableCell colspan="6">
                                        <div class="h-4 animate-pulse rounded bg-slate-100" />
                                    </TableCell>
                                </TableRow>
                            </template>

                            <TableRow
                                v-for="item in items"
                                v-else
                                :key="item.id"
                                class="cursor-pointer hover:bg-slate-50/70"
                                @click="router.visit(`/proxy/requests/${item.id}`)"
                            >
                                <TableCell>
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-mono text-[12px] text-blue-600">
                                            {{ shortId(item.request_id) }}
                                        </span>
                                        <span
                                            v-if="item.is_mocked"
                                            class="inline-flex items-center rounded bg-amber-100 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-amber-700"
                                            title="Ответ был замокан, реальный handler не вызывался"
                                        >Mock</span>
                                    </div>
                                </TableCell>
                                <TableCell class="text-[13px]">{{ item.endpoint ?? '—' }}</TableCell>
                                <TableCell>
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold"
                                        :class="STATUS_CLASS[item.status] ?? 'bg-slate-100 text-slate-600'"
                                    >
                                        {{ item.status_label }}
                                    </span>
                                </TableCell>
                                <TableCell class="font-mono text-[12px] text-slate-500">{{ item.ip ?? '—' }}</TableCell>
                                <TableCell class="text-[12px] text-slate-500">{{ fmt(item.created_at) }}</TableCell>
                                <TableCell class="max-w-[280px] truncate text-[12px] text-slate-500">
                                    {{ item.error ?? '—' }}
                                </TableCell>
                            </TableRow>

                            <TableRow v-if="!loading && items.length === 0">
                                <TableCell colspan="6" class="py-0">
                                    <EmptyState title="Нет запросов" subtitle="Входящие webhook-запросы появятся здесь">
                                        <template #icon><Zap :size="20" /></template>
                                    </EmptyState>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>

                    <ListPagination
                        v-model:current-page="page"
                        :total-pages="meta.last_page"
                        :total="meta.total"
                        :per-page="meta.per_page"
                    />
                </div>
            </div>
        </div>
    </AppShell>
</template>
