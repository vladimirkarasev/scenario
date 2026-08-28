<script setup lang="ts">
import {getCurrentScope, onScopeDispose, ref, watch} from 'vue'
import {Popover, PopoverContent, PopoverTrigger} from '@/components/ui/popover'
import {Input} from '@/components/ui/input'
import {X, Loader2} from 'lucide-vue-next'
import {suggestRepository} from '@/modules/scenario/repositories/suggestRepository'
import {useExpressionLabelBatch} from '@/modules/expression/composables/useExpressionLabelBatch'
import type {SuggestFieldConfig} from '@/modules/scenario/lib/scenario-block-fields'
import {useLatestRequest} from '@/composables/useLatestRequest'

type SuggestItem = Record<string, unknown>

const props = withDefaults(defineProps<{
  modelValue: SuggestItem | null
  proxyUuid: string
  labelTemplate?: string
  fields?: SuggestFieldConfig[]
  placeholder?: string
  count?: number
  disabled?: boolean
  error?: boolean
}>(), {
  labelTemplate: '',
  fields: () => [],
  placeholder: '',
  count: 5,
  disabled: false,
  error: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: SuggestItem | null]
}>()

const open = ref(false)
const query = ref('')
const items = ref<SuggestItem[]>([])
const {loading, error: loadError, execute} = useLatestRequest('Не удалось загрузить подсказки.')
const expressionLabels = useExpressionLabelBatch()
let searchTimer: ReturnType<typeof setTimeout> | null = null

function itemLabel(item: SuggestItem, index = 0): string {
  const rendered = expressionLabels.label(itemKey(item, index))
  if (rendered) return rendered
  const firstKey = Object.keys(item)[0]
  return firstKey ? String(item[firstKey] ?? '') : ''
}

function applyFieldDefaults(item: SuggestItem): SuggestItem {
  const result: SuggestItem = {...item}
  const data = result.data && typeof result.data === 'object' && !Array.isArray(result.data)
      ? {...result.data as Record<string, unknown>}
      : null

  for (const cfg of props.fields) {
    if (!cfg.defaultValue) continue
    if (cfg.key.startsWith('data.') && data) {
      const key = cfg.key.slice('data.'.length)
      if (data[key] == null) data[key] = cfg.defaultValue
    } else if (result[cfg.key] == null) {
      result[cfg.key] = cfg.defaultValue
    }
  }

  if (data) result.data = data

  return result
}

function extraSuggestParams(): Record<string, unknown> {
  const params: Record<string, unknown> = {count: props.count}
  for (const cfg of props.fields) {
    if (cfg.filterKey && cfg.defaultValue) {
      params[cfg.filterKey] = cfg.defaultValue
    }
  }
  return params
}

function itemKey(item: SuggestItem, index: number): string {
  return item.id != null ? String(item.id) : `suggest-${index}`
}

// Синхронизируем текст поля с выбранным значением только при реальном выборе —
// иначе обратный проп после emit(null) во время набора текста стирал бы то, что печатает пользователь.
watch(() => props.modelValue, (value) => {
  if (value) query.value = itemLabel(value)
}, {immediate: true})

watch([
  () => props.labelTemplate,
  () => props.modelValue,
  items,
], () => {
  const sources = items.value.map((item, index) => ({id: itemKey(item, index), data: item}))
  if (props.modelValue) {
    const id = itemKey(props.modelValue, 0)
    if (!sources.some((source) => source.id === id)) {
      sources.push({id, data: props.modelValue})
    }
  }
  void expressionLabels.load(props.labelTemplate, sources)
}, {immediate: true})

watch(expressionLabels.labels, () => {
  if (props.modelValue) query.value = itemLabel(props.modelValue)
})

async function loadItems(value: string): Promise<void> {
  if (!props.proxyUuid) return
  const result = await execute(() => suggestRepository.suggest(props.proxyUuid, value, extraSuggestParams()))
  if (result) items.value = result
  else if (loadError.value) items.value = []
}

function onInput(value: string): void {
  query.value = value
  if (props.modelValue) emit('update:modelValue', null)

  if (searchTimer) clearTimeout(searchTimer)
  if (!value) {
    open.value = false
    items.value = []
    return
  }
  open.value = true
  searchTimer = setTimeout(() => loadItems(value), 300)
}

function onFocus(): void {
  if (props.disabled || !query.value) return
  open.value = true
  if (items.value.length === 0) loadItems(query.value)
}

function onOpenChange(value: boolean): void {
  open.value = value
  if (!value && !props.modelValue) query.value = ''
}

function select(item: SuggestItem): void {
  const withDefaults = applyFieldDefaults(item)
  emit('update:modelValue', withDefaults)
  query.value = itemLabel(withDefaults)
  open.value = false
}

if (getCurrentScope()) {
  onScopeDispose(() => {
    if (searchTimer) clearTimeout(searchTimer)
  })
}

function clear(): void {
  emit('update:modelValue', null)
  query.value = ''
  items.value = []
  open.value = false
}
</script>

<template>
  <Popover :open="open" @update:open="onOpenChange">
    <PopoverTrigger as-child>
      <div class="relative">
        <Input
            :model-value="query"
            :disabled="disabled"
            :placeholder="placeholder || 'Начните вводить...'"
            class="h-9 pr-8 text-sm"
            :class="error ? 'border-destructive' : ''"
            @update:model-value="onInput(String($event))"
            @focus="onFocus"
            @keydown.escape="onOpenChange(false)"
        />
        <Loader2
            v-if="loading"
            class="absolute right-2.5 top-1/2 size-3.5 -translate-y-1/2 animate-spin text-muted-foreground"
        />
        <X
            v-else-if="query && !disabled"
            class="absolute right-2.5 top-1/2 size-3.5 -translate-y-1/2 cursor-pointer text-muted-foreground transition hover:text-foreground"
            @mousedown.prevent="clear"
        />
      </div>
    </PopoverTrigger>

    <PopoverContent
        align="start"
        :side-offset="4"
        class="w-[var(--reka-popover-trigger-width)] min-w-[200px] gap-0 p-0"
        @open-auto-focus.prevent
        @close-auto-focus.prevent
    >
      <div v-if="loading" class="flex items-center justify-center gap-2 py-6 text-sm text-muted-foreground">
        <Loader2 class="size-4 animate-spin"/>
        Загрузка...
      </div>

      <div v-else-if="items.length === 0" class="py-6 text-center text-sm text-muted-foreground">
        Ничего не найдено
      </div>

      <div v-else class="max-h-60 overflow-y-auto py-1">
        <button
            v-for="(item, index) in items"
            :key="itemKey(item, index)"
            type="button"
            class="flex w-full items-center px-3 py-2 text-left text-sm text-foreground/80 transition hover:bg-accent"
            @click="select(item)"
        >
          <span class="min-w-0 truncate">{{ itemLabel(item, index) }}</span>
        </button>
      </div>
    </PopoverContent>
  </Popover>
</template>
