<script setup lang="ts">
import {computed, onMounted, ref, watch} from 'vue'
import {Zap, Search, ChevronLeft, ChevronRight, X} from 'lucide-vue-next'
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription,
} from '@/components/ui/dialog'
import {Input} from '@/components/ui/input'
import {actionRepository} from '@/modules/actions/repositories/actionRepository'
import type {Action} from '@/modules/actions/types/action'

const props = defineProps<{
  open: boolean
  selectedId?: string
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  select: [action: Action]
}>()

const PER_PAGE = 10
const items = ref<Action[]>([])
const loading = ref(false)
const search = ref('')
const page = ref(1)

async function load(): Promise<void> {
  loading.value = true
  try {
    const qs = new URLSearchParams({'page[size]': '200'})
    items.value = await actionRepository.list(qs)
  } finally {
    loading.value = false
  }
}

onMounted(load)

watch(() => props.open, (val) => {
  if (val) {
    search.value = ''
    page.value = 1
    if (!items.value.length) load()
  }
})

const filtered = computed<Action[]>(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return items.value
  return items.value.filter(a =>
      a.name.toLowerCase().includes(q)
      || a.key.toLowerCase().includes(q)
      || a.code.toLowerCase().includes(q),
  )
})

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
const paged = computed(() => filtered.value.slice((page.value - 1) * PER_PAGE, page.value * PER_PAGE))
const pageStart = computed(() => (page.value - 1) * PER_PAGE + 1)
const pageEnd = computed(() => Math.min(page.value * PER_PAGE, filtered.value.length))

function select(action: Action): void {
  emit('select', action)
  emit('update:open', false)
}
</script>

<template>
  <Dialog :open="open" @update:open="emit('update:open', $event)">
    <DialogContent class="flex flex-col gap-0 p-0 sm:max-w-[560px] max-h-[80vh]">
      <DialogHeader class="shrink-0 border-b border-slate-100 px-5 py-4">
        <DialogTitle class="text-[15px] font-semibold text-slate-800">Выбор action</DialogTitle>
        <DialogDescription class="text-[13px] text-slate-500">
          Выберите action из модуля Actions
        </DialogDescription>
      </DialogHeader>

      <div class="shrink-0 border-b border-slate-100 px-4 py-3">
        <div class="relative">
          <Search class="pointer-events-none absolute left-3 top-1/2 size-3.5 -translate-y-1/2 text-slate-400"/>
          <Input v-model="search" class="h-8 pl-8 text-sm" placeholder="Поиск по названию, коду..." autofocus/>
          <button v-if="search" type="button"
                  class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                  @click="search = ''">
            <X class="size-3.5"/>
          </button>
        </div>
      </div>

      <div class="min-h-0 flex-1 overflow-y-auto">
        <div v-if="loading" class="flex items-center justify-center py-10 text-sm text-slate-400">Загрузка...</div>
        <div v-else-if="!filtered.length" class="flex items-center justify-center py-10 text-sm text-slate-400">Ничего
          не найдено
        </div>

        <ul v-else class="divide-y divide-slate-100">
          <li
              v-for="action in paged"
              :key="action.id"
              class="flex cursor-pointer items-start gap-3 px-4 py-3 transition hover:bg-slate-50"
              :class="action.id === selectedId ? 'bg-violet-50' : ''"
              @click="select(action)"
          >
            <div class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-lg"
                 :class="action.is_active ? 'bg-violet-100' : 'bg-slate-100'">
              <Zap class="size-3.5" :class="action.is_active ? 'text-violet-600' : 'text-slate-400'"/>
            </div>
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-2">
                <span class="font-medium text-[13px] text-slate-800 leading-snug">{{ action.name }}</span>
                <span class="font-mono text-[11px] text-slate-400 leading-snug">{{ action.code || action.key }}</span>
              </div>
              <p v-if="action.description" class="mt-0.5 truncate text-[12px] text-slate-500 leading-snug">
                {{ action.description }}
              </p>
              <p v-if="action.input_fields.length" class="mt-0.5 text-[11px] text-slate-400">
                {{ action.input_fields.length }} input-полей
              </p>
            </div>
            <div v-if="action.id === selectedId" class="mt-0.5 shrink-0">
              <span class="inline-block size-2 rounded-full bg-violet-500"/>
            </div>
          </li>
        </ul>
      </div>

      <div v-if="totalPages > 1"
           class="shrink-0 flex items-center justify-between border-t border-slate-100 px-4 py-2.5">
        <span class="text-[12px] text-slate-400">{{ pageStart }}–{{ pageEnd }} из {{ filtered.length }}</span>
        <div class="flex items-center gap-1">
          <button type="button"
                  class="flex size-7 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-30"
                  :disabled="page <= 1" @click="page--">
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
            <span v-else-if="(p === page - 3 && p > 1) || (p === page + 3 && p < totalPages)"
                  class="flex size-7 items-center justify-center text-[12px] text-slate-400">…</span>
          </template>
          <button type="button"
                  class="flex size-7 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-30"
                  :disabled="page >= totalPages" @click="page++">
            <ChevronRight class="size-4"/>
          </button>
        </div>
      </div>

      <div class="shrink-0 flex justify-end border-t border-slate-100 px-4 py-3">
        <button type="button" class="rounded-lg px-4 py-2 text-[13px] text-slate-500 transition hover:bg-slate-100"
                @click="emit('update:open', false)">Отмена
        </button>
      </div>
    </DialogContent>
  </Dialog>
</template>
