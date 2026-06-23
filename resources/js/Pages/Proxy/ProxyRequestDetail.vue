<script setup lang="ts">
import AppShell from '@/layouts/AppShell.vue'
import {useDashboardNavigation} from '@/composables/useDashboardNavigation'
import {webhookRequestRepository} from '@/modules/proxy/repositories/webhookRequestRepository'
import type {WebhookRequestLogDetail} from '@/modules/proxy/types/webhook'
import {Head, Link} from '@inertiajs/vue3'
import {ArrowLeft, Loader2} from 'lucide-vue-next'
import {computed, onMounted, ref} from 'vue'

const props = defineProps<{ requestLogId: string }>()
const {navigationItems} = useDashboardNavigation()

const item = ref<WebhookRequestLogDetail | null>(null)
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    item.value = await webhookRequestRepository.get(props.requestLogId)
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : 'Не удалось загрузить запрос'
  } finally {
    loading.value = false
  }
})

const STATUS_CLASS: Record<string, string> = {
  received: 'bg-slate-100 text-slate-700',
  accepted: 'bg-blue-50 text-blue-700',
  rejected: 'bg-amber-50 text-amber-700',
  failed: 'bg-red-50 text-red-700',
  processed: 'bg-emerald-50 text-emerald-700',
}

const statusClass = computed(() =>
    STATUS_CLASS[item.value?.status ?? ''] ?? 'bg-slate-100 text-slate-700',
)


const timelineSteps = computed(() => {
  const status = item.value?.status ?? ''
  if (status === 'received' || status === 'accepted' || status === 'processed') {
    return [
      {label: 'Получен', done: true},
      {label: 'Принят', done: status === 'accepted' || status === 'processed'},
      {label: 'Обработан', done: status === 'processed'},
    ]
  }
  if (status === 'rejected') {
    return [
      {label: 'Получен', done: true},
      {label: 'Отклонён', done: true, isError: true},
    ]
  }
  if (status === 'failed') {
    return [
      {label: 'Получен', done: true},
      {label: 'Принят', done: true},
      {label: 'Ошибка', done: true, isError: true},
    ]
  }
  return [{label: 'Получен', done: true}]
})

const jsonSections = computed(() => {
  if (!item.value) return []
  return ([
    {key: 'payload', label: 'Payload', data: item.value.payload},
    {key: 'normalized_data', label: 'Нормализованные данные', data: item.value.normalized_data},
    {key: 'query_params', label: 'Query параметры', data: item.value.query_params},
    {key: 'headers_masked', label: 'Заголовки', data: item.value.headers_masked},
    {key: 'message_box', label: 'Message Box', data: item.value.message_box},
    {key: 'response_body', label: 'Тело ответа', data: item.value.response_body},
    {key: 'response_headers', label: 'Заголовки ответа', data: item.value.response_headers},
  ] as const).filter(s => s.data !== null && s.data !== undefined)
})

function fmt(iso: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('ru-RU', {dateStyle: 'medium', timeStyle: 'medium'})
}
</script>

<template>
  <Head title="Webhook запрос"/>

  <AppShell title="Webhook запрос" :navigation-items="navigationItems">
    <div class="min-h-full bg-slate-50">
      <div class="mx-auto max-w-4xl px-6 py-8">
        <Link
            href="/proxy/requests"
            class="mb-6 inline-flex items-center gap-1.5 text-[13px] text-slate-500 hover:text-slate-900"
        >
          <ArrowLeft :size="14"/>
          Все запросы
        </Link>

        <!-- Loading -->
        <div v-if="loading" class="flex items-center justify-center py-24 text-slate-400">
          <Loader2 :size="24" class="animate-spin"/>
        </div>

        <!-- Error -->
        <div v-else-if="error" class="rounded-2xl border border-red-100 bg-red-50 px-6 py-5 text-[13px] text-red-700">
          {{ error }}
        </div>

        <template v-else-if="item">
          <!-- ── Header ──────────────────────────────────────────── -->
          <div class="mb-5 flex items-start justify-between gap-4">
            <div class="min-w-0">
              <div class="truncate font-mono text-[15px] font-bold text-slate-900">
                {{ item.request_id }}
              </div>
              <div class="mt-1 text-[12px] text-slate-400">
                <span v-if="item.endpoint">{{ item.endpoint }}</span>
                <span v-if="item.ip"> · {{ item.ip }}</span>
                <span v-if="item.created_at"> · {{ fmt(item.created_at) }}</span>
              </div>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                            <span
                                v-if="item.is_mocked"
                                class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-amber-700"
                                title="Ответ был замокан, реальный handler не вызывался"
                            >Mock</span>
              <span
                  class="inline-flex items-center rounded-full px-3 py-1 text-[12px] font-semibold"
                  :class="statusClass"
              >
                                {{ item.status_label }}
                            </span>
            </div>
          </div>

          <!-- ── Timeline ────────────────────────────────────────── -->
          <div
              class="mb-5 overflow-hidden rounded-2xl border border-slate-200 bg-white px-6 py-5 shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
            <div class="flex items-center gap-0">
              <template v-for="(step, i) in timelineSteps" :key="step.label">
                <div class="flex flex-col items-center">
                  <div
                      class="flex h-7 w-7 items-center justify-center rounded-full text-[11px] font-bold"
                      :class="{
                                            'bg-red-100 text-red-700': step.done && step.isError,
                                            'bg-blue-600 text-white': step.done && !step.isError,
                                            'bg-slate-100 text-slate-400': !step.done,
                                        }"
                  >
                    {{ i + 1 }}
                  </div>
                  <div class="mt-1.5 text-[11px] font-medium"
                       :class="{
                                             'text-red-600': step.done && step.isError,
                                             'text-slate-700': step.done && !step.isError,
                                             'text-slate-400': !step.done,
                                         }"
                  >
                    {{ step.label }}
                  </div>
                </div>
                <div
                    v-if="i < timelineSteps.length - 1"
                    class="mb-4 h-px flex-1"
                    :class="timelineSteps[i + 1]?.done ? 'bg-blue-200' : 'bg-slate-100'"
                />
              </template>
            </div>
          </div>

          <!-- ── Metadata ────────────────────────────────────────── -->
          <div
              class="mb-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
            <div class="grid grid-cols-2 divide-x divide-slate-100 md:grid-cols-4">
              <div
                  v-for="cell in [
                                    { label: 'Метод', value: item.method ?? '—' },
                                    { label: 'Код ответа', value: item.response_code ?? '—' },
                                    { label: 'Получен', value: fmt(item.received_at) },
                                    { label: 'Обработан', value: fmt(item.processed_at) },
                                ]"
                  :key="cell.label"
                  class="px-5 py-4"
              >
                <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                  {{ cell.label }}
                </div>
                <div class="mt-1 text-[13px] font-medium text-slate-900">{{ cell.value }}</div>
              </div>
            </div>
          </div>

          <!-- ── Error message ───────────────────────────────────── -->
          <div v-if="item.error" class="mb-4 rounded-2xl border border-red-100 bg-red-50 px-5 py-4">
            <div class="mb-1 text-[11px] font-bold uppercase tracking-wider text-red-500">Ошибка</div>
            <p class="text-[13px] leading-relaxed text-red-800">{{ item.error }}</p>
          </div>

          <!-- ── JSON sections ───────────────────────────────────── -->
          <div class="space-y-3">
            <div
                v-for="section in jsonSections"
                :key="section.key"
                class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04)]"
            >
              <div class="border-b border-slate-100 px-5 py-3 text-[12px] font-semibold text-slate-700">
                {{ section.label }}
              </div>
              <pre class="max-h-72 overflow-auto px-5 py-4 font-mono text-[11px] leading-relaxed text-slate-700">{{
                  JSON.stringify(section.data, null, 2)
                }}</pre>
            </div>
          </div>
        </template>
      </div>
    </div>
  </AppShell>
</template>
