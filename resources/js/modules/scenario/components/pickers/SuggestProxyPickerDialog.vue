<script setup lang="ts">
import {ref, computed, watch} from 'vue'
import {Lightbulb, Search, X, ChevronLeft, ChevronRight} from 'lucide-vue-next'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
} from '@/components/ui/dialog'
import {Input} from '@/components/ui/input'
import {useSuggestProxyPicker} from '@/modules/scenario/composables/useSuggestProxyPicker'
import type {WebhookEndpoint} from '@/modules/proxy/types/webhook'

const props = defineProps<{
  open: boolean
  selectedUuid?: string
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  select: [proxy: WebhookEndpoint]
}>()

const PER_PAGE = 15
const search = ref('')
const page = ref(1)

const {proxies, loading, load} = useSuggestProxyPicker(() => props.selectedUuid ?? '')

watch(() => props.open, (val) => {
  if (val) {
    search.value = ''
    page.value = 1
    void load()
  }
})

const filtered = computed(() => {
  const q = search.value.toLowerCase().trim()
  if (!q) return proxies.value
  return proxies.value.filter((p) =>
      p.name.toLowerCase().includes(q) ||
      p.code.toLowerCase().includes(q),
  )
})

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const paged = computed(() => filtered.value.slice((page.value - 1) * PER_PAGE, page.value * PER_PAGE))
const pageStart = computed(() => (page.value - 1) * PER_PAGE + 1)
const pageEnd = computed(() => Math.min(page.value * PER_PAGE, filtered.value.length))

watch(search, () => {
  page.value = 1
})
watch(totalPages, (n) => {
  if (page.value > n) page.value = n
})

function select(proxy: WebhookEndpoint): void {
  emit('select', proxy)
  emit('update:open', false)
}
</script>

<template>
  <Dialog :open="open" @update:open="emit('update:open', $event)">
    <DialogContent class="flex flex-col gap-0 p-0 sm:max-w-[540px] max-h-[80vh]">
      <DialogHeader class="shrink-0 border-b border-slate-100 px-5 py-4">
        <DialogTitle class="text-[15px] font-semibold text-slate-800">Выбор интеграции</DialogTitle>
        <DialogDescription class="text-[13px] text-slate-500">
          Выберите интеграцию типа «Подсказки» из списка
        </DialogDescription>
      </DialogHeader>

      <!-- Search -->
      <div class="shrink-0 border-b border-slate-100 px-4 py-3">
        <div class="relative">
          <Search class="pointer-events-none absolute left-3 top-1/2 size-3.5 -translate-y-1/2 text-slate-400"/>
          <Input
              v-model="search"
              class="h-8 pl-8 text-sm"
              placeholder="Поиск по названию..."
              autofocus
          />
          <button
              v-if="search"
              type="button"
              class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
              @click="search = ''"
          >
            <X class="size-3.5"/>
          </button>
        </div>
      </div>

      <!-- List -->
      <div class="min-h-0 flex-1 overflow-y-auto">
        <div v-if="loading" class="flex items-center justify-center py-10 text-sm text-slate-400">
          Загрузка...
        </div>
        <div v-else-if="!filtered.length" class="flex items-center justify-center py-10 text-sm text-slate-400">
          Ничего не найдено
        </div>
        <ul v-else class="divide-y divide-slate-100">
          <li
              v-for="proxy in paged"
              :key="proxy.uuid"
              class="flex cursor-pointer items-start gap-3 px-4 py-3 transition hover:bg-slate-50"
              :class="proxy.uuid === selectedUuid ? 'bg-violet-50' : ''"
              @click="select(proxy)"
          >
            <div class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-lg bg-slate-100">
              <Lightbulb class="size-3.5 text-slate-500"/>
            </div>
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-2">
                <span class="text-[13px] font-medium leading-snug text-slate-800">{{ proxy.name }}</span>
                <span class="font-mono text-[11px] leading-snug text-slate-400">{{ proxy.code }}</span>
              </div>
              <p v-if="proxy.description" class="mt-0.5 truncate text-[12px] leading-snug text-slate-500">
                {{ proxy.description }}
              </p>
            </div>
            <div v-if="proxy.uuid === selectedUuid" class="mt-0.5 shrink-0">
              <span class="inline-block size-2 rounded-full bg-violet-500"/>
            </div>
          </li>
        </ul>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1"
           class="flex shrink-0 items-center justify-between border-t border-slate-100 px-4 py-2.5">
        <span class="text-[12px] text-slate-400">{{ pageStart }}–{{ pageEnd }} из {{ filtered.length }}</span>
        <div class="flex items-center gap-1">
          <button
              type="button"
              class="flex size-7 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-30"
              :disabled="page <= 1"
              @click="page--"
          >
            <ChevronLeft class="size-4"/>
          </button>
          <template v-for="p in totalPages" :key="p">
            <button
                v-if="Math.abs(p - page) <= 2 || p === 1 || p === totalPages"
                type="button"
                class="flex size-7 items-center justify-center rounded-lg text-[12px] font-medium transition"
                :class="p === page ? 'bg-violet-600 text-white' : 'text-slate-500 hover:bg-slate-100'"
                @click="page = p"
            >
              {{ p }}
            </button>
            <span
                v-else-if="(p === page - 3 && p > 1) || (p === page + 3 && p < totalPages)"
                class="flex size-7 items-center justify-center text-[12px] text-slate-400"
            >…</span>
          </template>
          <button
              type="button"
              class="flex size-7 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-30"
              :disabled="page >= totalPages"
              @click="page++"
          >
            <ChevronRight class="size-4"/>
          </button>
        </div>
      </div>

      <!-- Footer -->
      <div class="flex shrink-0 justify-end border-t border-slate-100 px-4 py-3">
        <button
            type="button"
            class="rounded-lg px-4 py-2 text-[13px] text-slate-500 transition hover:bg-slate-100"
            @click="emit('update:open', false)"
        >
          Отмена
        </button>
      </div>
    </DialogContent>
  </Dialog>
</template>
