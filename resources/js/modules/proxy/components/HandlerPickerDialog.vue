<script setup lang="ts">
import {computed, ref, watch} from 'vue'
import {useVirtualizer} from '@tanstack/vue-virtual'
import {Search, X, Zap} from 'lucide-vue-next'
import {Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription} from '@/components/ui/dialog'
import {Input} from '@/components/ui/input'
import type {HandlerOption} from '@/modules/proxy/types/webhook'

const props = defineProps<{
  open: boolean
  items: HandlerOption[]
  selectedClass?: string | null
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  select: [handler: HandlerOption]
}>()

const search = ref('')

watch(() => props.open, (val) => {
  if (val) search.value = ''
})

type FlatRow =
    | { type: 'group', key: string, group: string }
    | { type: 'handler', key: string, group: string, handler: HandlerOption }

const flatRows = computed<FlatRow[]>(() => {
  const q = search.value.trim().toLowerCase()
  const filtered = q
      ? props.items.filter(h =>
          h.label.toLowerCase().includes(q)
          || h.group.toLowerCase().includes(q)
          || h.class.toLowerCase().includes(q),
      )
      : props.items

  const map = new Map<string, HandlerOption[]>()
  for (const h of filtered) {
    const arr = map.get(h.group) ?? []
    arr.push(h)
    map.set(h.group, arr)
  }

  const rows: FlatRow[] = []
  for (const [group, handlers] of map) {
    rows.push({type: 'group', key: `group:${group}`, group})
    for (const handler of handlers) {
      rows.push({type: 'handler', key: handler.class, group, handler})
    }
  }
  return rows
})

const scrollParent = ref<HTMLElement | null>(null)

const rowVirtualizer = useVirtualizer(computed(() => ({
  count: flatRows.value.length,
  getScrollElement: () => scrollParent.value,
  estimateSize: (index: number) => (flatRows.value[index]?.type === 'group' ? 28 : 52),
  overscan: 12,
  getItemKey: (index: number) => flatRows.value[index]?.key ?? index,
})))

const virtualRows = computed(() => rowVirtualizer.value.getVirtualItems())
const virtualNodes = computed(() =>
    virtualRows.value.map(vRow => ({vRow, row: flatRows.value[vRow.index]!})),
)
const totalSize = computed(() => rowVirtualizer.value.getTotalSize())

function select(handler: HandlerOption): void {
  emit('select', handler)
  emit('update:open', false)
}
</script>

<template>
  <Dialog :open="open" @update:open="emit('update:open', $event)">
    <DialogContent class="flex flex-col gap-0 p-0 sm:max-w-[520px] max-h-[80vh]">
      <DialogHeader class="shrink-0 border-b border-slate-100 px-5 py-4">
        <DialogTitle class="text-[15px] font-semibold text-slate-800">Выбор обработчика</DialogTitle>
        <DialogDescription class="text-[13px] text-slate-500">
          {{ items.length }} обработчиков доступно
        </DialogDescription>
      </DialogHeader>

      <div class="shrink-0 border-b border-slate-100 px-4 py-3">
        <div class="relative">
          <Search class="pointer-events-none absolute left-3 top-1/2 size-3.5 -translate-y-1/2 text-slate-400" />
          <Input v-model="search" class="h-8 pl-8 text-sm" placeholder="Поиск по обработчикам..." autofocus />
          <button v-if="search" type="button"
                  class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                  @click="search = ''">
            <X class="size-3.5" />
          </button>
        </div>
      </div>

      <div v-if="!flatRows.length" class="flex items-center justify-center py-10 text-sm text-slate-400">
        Ничего не найдено
      </div>

      <div v-else ref="scrollParent" class="min-h-0 flex-1 overflow-y-auto">
        <div :style="{height: `${totalSize}px`, position: 'relative'}">
          <div
              v-for="{vRow, row} in virtualNodes"
              :key="row.key"
              :style="{position: 'absolute', top: 0, left: 0, width: '100%', transform: `translateY(${vRow.start}px)`}"
          >
            <div v-if="row.type === 'group'"
                 class="bg-slate-50 px-4 py-1.5 text-[11px] font-bold uppercase tracking-wider text-slate-400">
              {{ row.group }}
            </div>
            <div
                v-else
                class="flex cursor-pointer items-center gap-3 border-b border-slate-100 px-4 py-2.5 transition hover:bg-slate-50"
                :class="row.handler.class === selectedClass ? 'bg-blue-50' : ''"
                @click="select(row.handler)"
            >
              <div class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-blue-100">
                <Zap class="size-3.5 text-blue-600" />
              </div>
              <div class="min-w-0 flex-1">
                <div class="text-[13px] font-medium text-slate-800 leading-snug">{{ row.handler.label }}</div>
                <div class="truncate font-mono text-[11px] text-slate-400 leading-snug">{{ row.handler.class }}</div>
              </div>
            </div>
          </div>
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
