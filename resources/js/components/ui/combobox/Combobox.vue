<script setup>
import { ref, computed } from 'vue'
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover'
import { Input } from '@/components/ui/input'
import { Check, ChevronsUpDown } from 'lucide-vue-next'
import { cn } from '@/lib/utils'

const props = defineProps({
  modelValue:  { type: String,  default: '' },
  items:       { type: Array,   default: () => [] },
  placeholder: { type: String,  default: 'Выберите...' },
  emptyText:   { type: String,  default: 'Ничего не найдено' },
  disabled:    { type: Boolean, default: false },
  size:        { type: String,  default: 'default' }, // 'default' | 'sm'
})

const emit = defineEmits(['update:modelValue'])

const open = ref(false)
const search = ref('')

const selectedLabel = computed(() =>
  props.items.find((i) => i.value === props.modelValue)?.label ?? '',
)

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  return q ? props.items.filter((i) => i.label.toLowerCase().includes(q)) : props.items
})

function select(value) {
  emit('update:modelValue', value)
  open.value = false
  search.value = ''
}

function onOpenChange(val) {
  open.value = val
  if (!val) search.value = ''
}
</script>

<template>
  <Popover :open="open" @update:open="onOpenChange">
    <PopoverTrigger as-child>
      <button
        type="button"
        :disabled="disabled"
        :class="cn(
          'flex w-full items-center justify-between gap-2 rounded-xl border border-slate-200 bg-white px-3 text-left transition hover:border-slate-300 disabled:cursor-not-allowed disabled:opacity-50',
          size === 'sm' ? 'h-8 text-[12px]' : 'h-9 text-[13px]',
        )"
      >
        <span class="truncate" :class="selectedLabel ? 'text-slate-800' : 'text-slate-400'">
          {{ selectedLabel || placeholder }}
        </span>
        <ChevronsUpDown class="size-3.5 shrink-0 text-slate-400" />
      </button>
    </PopoverTrigger>

    <PopoverContent
      align="start"
      :side-offset="4"
      class="w-[var(--reka-popover-trigger-width)] min-w-[180px] gap-0 p-0"
    >
      <div class="p-2 pb-1">
        <Input
          v-model="search"
          placeholder="Поиск..."
          @keydown.enter.prevent="filtered.length === 1 && select(filtered[0].value)"
          @keydown.escape="onOpenChange(false)"
        />
      </div>

      <div class="max-h-52 overflow-y-auto p-1">
        <p v-if="filtered.length === 0" class="py-3 text-center text-[12px] text-slate-400">
          {{ emptyText }}
        </p>
        <button
          v-for="item in filtered"
          :key="item.value"
          type="button"
          class="flex w-full cursor-pointer items-center justify-between gap-2 rounded-lg px-2.5 py-1.5 text-[13px] text-slate-700 outline-none hover:bg-slate-100 focus:bg-slate-100"
          :class="item.value === modelValue ? 'font-medium text-slate-900' : ''"
          @click="select(item.value)"
        >
          <span class="truncate">{{ item.label }}</span>
          <Check v-if="item.value === modelValue" class="size-3.5 shrink-0 text-blue-600" />
        </button>
      </div>
    </PopoverContent>
  </Popover>
</template>
