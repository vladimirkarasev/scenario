<script setup lang="ts">
import AppShell from '@/layouts/AppShell.vue'
import EmptyState from '@/components/EmptyState.vue'
import ListPagination from '@/components/ListPagination.vue'
import PageHeader from '@/components/PageHeader.vue'
import SearchInput from '@/components/SearchInput.vue'
import {
    FormActions, FormBody, FormError, FormInput, FormJsonInput, FormMockVariants,
    FormSection, FormTextarea, FormToggle,
} from '@/components/form'
import { useDashboardNavigation } from '@/composables/useDashboardNavigation'
import { useWebhookList } from '@/modules/proxy/composables/useWebhookList'
import { useWebhookModal } from '@/modules/proxy/composables/useWebhookModal'
import { Head, router } from '@inertiajs/vue3'
import {
    DropdownMenu, DropdownMenuContent, DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
    Check, Copy, FlaskConical, List, Loader2, MoreHorizontal,
    Pencil, Shield, ShieldOff, X, Zap,
} from 'lucide-vue-next'
import { computed, ref } from 'vue'

const { navigationItems } = useDashboardNavigation()

const { loading, endpoints, search, page, filtered, paged, totalPages, PER_PAGE, load } = useWebhookList()
const {
    editing, showModal, saving, editError, errors, form,
    fields, loadingFields,
    openEdit, close, save, toggleActive,
} = useWebhookModal(load)

const activeCount   = computed(() => endpoints.value.filter(e => e.is_active).length)
const inactiveCount = computed(() => endpoints.value.filter(e => !e.is_active).length)

const copied = ref<string | null>(null)

async function copyText(text: string, key: string) {
    await navigator.clipboard.writeText(text).catch(() => {})
    copied.value = key
    setTimeout(() => { copied.value = null }, 1500)
}

</script>

<template>
    <Head title="Proxy-эндпоинты" />

    <AppShell title="Proxy-эндпоинты" :navigation-items="navigationItems">
        <div class="min-h-full bg-slate-50">
            <div class="mx-auto max-w-5xl px-6 py-8">
<!-- Header -->
                <PageHeader title="Proxy-эндпоинты" subtitle="Webhook-приёмники для синхронизации справочников. Добавление — через миграции, здесь только редактирование." />

                <!-- Stats -->
                <div class="mb-6 grid grid-cols-3 gap-4">
                    <div class="rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
                        <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Всего</div>
                        <div class="mt-1.5 text-3xl font-bold tabular-nums text-slate-900">{{ endpoints.length }}</div>
                    </div>
                    <div class="rounded-xl border border-emerald-100 bg-emerald-50 px-5 py-4 shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
                        <div class="text-[11px] font-semibold uppercase tracking-wider text-emerald-600">Активных</div>
                        <div class="mt-1.5 text-3xl font-bold tabular-nums text-emerald-700">{{ activeCount }}</div>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
                        <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Отключённых</div>
                        <div class="mt-1.5 text-3xl font-bold tabular-nums text-slate-400">{{ inactiveCount }}</div>
                    </div>
                </div>

                <!-- Search -->
                <div class="mb-4">
                    <SearchInput v-model="search" placeholder="Поиск по эндпоинтам..." />
                </div>

                <!-- Table -->
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
<!-- Loading -->
                    <div v-if="loading" class="flex items-center justify-center py-16 text-slate-400">
                        <Loader2 :size="20" class="animate-spin" />
                    </div>

                    <template v-else>
                        <!-- Table header -->
                        <div
                            class="grid border-b border-slate-100 px-5 py-3"
                            style="grid-template-columns: 1fr 180px 110px 40px"
                        >
                            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Эндпоинт</div>
                            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Code</div>
                            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Статус</div>
                            <div />
                        </div>

                        <!-- Empty state -->
                        <EmptyState v-if="!filtered.length" title="Ничего не найдено" subtitle="Попробуйте изменить поисковый запрос">
                            <template #icon><Zap :size="20" /></template>
                        </EmptyState>

                        <!-- Rows -->
                        <div
                            v-for="(ep, idx) in paged"
                            :key="ep.id"
                            class="group relative grid cursor-pointer items-center px-5 py-4 transition-colors hover:bg-slate-50/70"
                            :class="idx !== paged.length - 1 ? 'border-b border-slate-100' : ''"
                            style="grid-template-columns: 1fr 180px 110px 40px"
                        >
                            <!-- Name + description -->
                            <div class="min-w-0 pr-4">
                                <div class="flex items-center gap-2" @click="openEdit(ep)">
                                    <div
                                        class="flex h-7 w-7 flex-none items-center justify-center rounded-lg text-[11px] font-bold text-white"
                                        :class="ep.is_active ? 'bg-blue-600' : 'bg-slate-300'"
                                    >
                                        {{ ep.name.slice(0, 1).toUpperCase() }}
                                    </div>
                                    <span class="truncate text-[13px] font-semibold text-slate-900">{{ ep.name }}</span>
                                    <span
                                        v-if="ep.is_mocked"
                                        class="inline-flex h-5 items-center gap-1 rounded-full bg-amber-50 px-2 text-[11px] font-semibold text-amber-700"
                                        title="Возвращает мок-ответ вместо вызова handler"
                                    >
                                        <FlaskConical :size="11" /> Мок
                                    </span>
                                </div>
                                <p class="mt-0.5 truncate pl-9 text-[12px] text-slate-400">{{ ep.description }}</p>
                            </div>

                            <!-- Code -->
                            <div class="min-w-0 pr-3">
                                <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 font-mono text-[11px] text-slate-600">
                                    {{ ep.code }}
                                </span>
                            </div>

                            <!-- Status -->
                            <div>
                                <span
                                    class="inline-flex h-5 items-center rounded-full px-2 text-[11px] font-semibold"
                                    :class="ep.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-400'"
                                >
                                    {{ ep.is_active ? 'Активен' : 'Отключён' }}
                                </span>
                            </div>

                            <!-- Actions menu -->
                            <div class="flex justify-end">
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <button class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700">
                                            <MoreHorizontal :size="15" />
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end" class="w-48">
                                        <DropdownMenuItem @click.stop="openEdit(ep)">
                                            <Pencil class="mr-2 h-4 w-4 text-slate-400" /> Редактировать
                                        </DropdownMenuItem>
                                        <DropdownMenuItem @click.stop="router.visit(`/proxy/requests?filter[endpoint_id]=${ep.id}`)">
                                            <List class="mr-2 h-4 w-4 text-slate-400" /> Запросы
                                        </DropdownMenuItem>
                                        <DropdownMenuItem @click.stop="toggleActive(ep)">
                                            <component :is="ep.is_active ? ShieldOff : Shield" class="mr-2 h-4 w-4 text-slate-400" />
                                            {{ ep.is_active ? 'Отключить' : 'Активировать' }}
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </div>

                        <!-- Pagination -->
                        <ListPagination v-model:current-page="page" :total-pages="totalPages" :total="filtered.length" :per-page="PER_PAGE" />
                    </template>
                </div>
</div>
        </div>
    </AppShell>

    <!-- ── Edit modal ──────────────────────────────────────────────────── -->
    <Teleport to="body">
        <div
            v-if="showModal && editing"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-sm"
            @click.self="close"
        >
            <div class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_24px_64px_-12px_rgba(15,23,42,0.2)]">
<!-- Modal header -->
                <div class="flex shrink-0 items-start justify-between border-b border-slate-100 px-6 py-4">
                    <div class="min-w-0">
                        <div class="text-[15px] font-bold text-slate-900">{{ editing.name }}</div>
                        <div class="mt-0.5 font-mono text-[11px] text-slate-400">{{ editing.code }}</div>
                    </div>
                    <button class="ml-4 shrink-0 text-slate-400 transition hover:text-slate-700" @click="close">
                        <X :size="18" />
                    </button>
                </div>

                <!-- Modal body -->
                <form class="flex-1 overflow-y-auto" @submit.prevent="save" novalidate>
                    <FormBody>
                        <FormError :message="editError" />
                        <FormSection>
                            <FormInput
                                v-model="form.name"
                                label="Название"
                                required
                                :error="errors.name"
                            />
                            <FormTextarea
                                v-model="form.description"
                                label="Описание"
                                :rows="2"
                                :error="errors.description"
                            />
                            <FormToggle
                                v-model="form.is_active"
                                label="Активен"
                                description="Эндпоинт принимает входящие запросы"
                            />
                        </FormSection>

                    <!-- Divider -->
                    <div class="border-t border-slate-100" />

                    <!-- System info (read-only) -->
                    <div>
                        <div class="mb-2.5 text-[12px] font-semibold text-slate-700">Системные данные</div>
                        <div class="space-y-1.5 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <div
                                v-for="row in [
                                    { label: 'UUID', value: editing.uuid },
                                    { label: 'Code', value: editing.code },
                                    { label: 'Handler', value: editing.handler_class },
                                ]"
                                :key="row.label"
                                class="flex items-center gap-3 text-[12px]"
                            >
                                <span class="w-14 shrink-0 text-slate-400">{{ row.label }}</span>
                                <span class="min-w-0 flex-1 truncate font-mono text-slate-700">{{ row.value }}</span>
                                <button
                                    type="button"
                                    class="shrink-0 text-slate-400 transition hover:text-slate-700"
                                    @click="copyText(row.value, row.label)"
                                >
                                    <Check v-if="copied === row.label" :size="13" class="text-emerald-500" />
                                    <Copy v-else :size="13" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Fields table -->
                    <template v-if="loadingFields || fields.length">
                        <div class="border-t border-slate-100" />
                        <div>
                            <div class="mb-2.5 text-[12px] font-semibold text-slate-700">Поля</div>
                            <div v-if="loadingFields" class="flex items-center gap-2 py-2 text-[12px] text-slate-400">
                                <Loader2 :size="13" class="animate-spin" /> Загружаем поля…
                            </div>
                            <div v-else class="overflow-hidden rounded-xl border border-slate-200">
                                <div class="grid border-b border-slate-100 px-4 py-2" style="grid-template-columns: 160px 1fr 80px 1fr">
                                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Key</div>
                                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Название</div>
                                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Тип</div>
                                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Пример</div>
                                </div>
                                <div
                                    v-for="(f, i) in fields"
                                    :key="f.key"
                                    class="grid items-center px-4 py-2 text-[12px]"
                                    :class="i !== fields.length - 1 ? 'border-b border-slate-100' : ''"
                                    style="grid-template-columns: 160px 1fr 80px 1fr"
                                >
                                    <div class="flex items-center gap-1.5 font-mono text-slate-700">
                                        {{ f.key }}
                                        <span v-if="f.required" class="rounded bg-blue-50 px-1 text-[10px] font-bold text-blue-600">req</span>
                                    </div>
                                    <div class="text-slate-600">{{ f.label }}</div>
                                    <div>
                                        <span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] text-slate-500">{{ f.type }}</span>
                                    </div>
                                    <div class="truncate font-mono text-slate-400">{{ f.example ?? '—' }}</div>
                                </div>
                            </div>
                        </div>
                    </template>

                        <div class="border-t border-slate-100" />
                        <FormJsonInput
                            v-model="form.config"
                            label="Config (JSON)"
                            :rows="5"
                            :error="errors.config"
                        />

                        <div class="border-t border-slate-100" />
                        <div class="mb-2.5 flex items-center gap-2 text-[12px] font-semibold text-slate-700">
                            <FlaskConical :size="13" class="text-amber-600" /> Мок-ответы
                        </div>
                        <FormToggle
                            v-model="form.is_mocked"
                            label="Использовать мок-ответ"
                            description="При включении handler не вызывается — возвращается первый подходящий вариант."
                        />
                        <FormMockVariants v-model="form.mocks" :error="errors.mocks" />
                    </FormBody>
                </form>
                <FormActions
                    :submitting="saving"
                    :submit-label="saving ? 'Сохраняем…' : 'Сохранить'"
                    @cancel="close"
                    @submit="save"
                />
</div>
        </div>
    </Teleport>
</template>
