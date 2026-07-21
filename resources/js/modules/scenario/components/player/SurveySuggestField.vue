<script setup lang="ts">
import {computed, nextTick, ref} from 'vue'
import {Popover, PopoverContent, PopoverTrigger} from '@/components/ui/popover'
import {Input} from '@/components/ui/input'
import {ChevronsUpDown, X, Check, Loader2} from 'lucide-vue-next'
import {suggestRepository} from '@/modules/scenario/repositories/suggestRepository'

type SuggestItem = Record<string, unknown>
type IncomingModelValue = SuggestItem | SuggestItem[] | null

const props = withDefaults(defineProps<{
  modelValue: IncomingModelValue
  proxyUuid: string
  labelField?: string
  placeholder?: string
  multiple?: boolean
  disabled?: boolean
  error?: boolean
}>(), {
  labelField: '',
  placeholder: '',
  multiple: false,
  disabled: false,
  error: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: SuggestItem | SuggestItem[] | null]
}>()

const open = ref(false)
const search = ref('')
const searchRef = ref<InstanceType<typeof Input> | null>(null)
const items = ref<SuggestItem[]>([])
const loading = ref(false)
let searchTimer: ReturnType<typeof setTimeout> | null = null

function itemLabel(item: SuggestItem): string {
  if (props.labelField && item[props.labelField] != null) return String(item[props.labelField])
  const firstKey = Object.keys(item)[0]
  return firstKey ? String(item[firstKey] ?? '') : ''
}

function itemKey(item: SuggestItem): string {
  return item.id != null ? String(item.id) : JSON.stringify(item)
}

function currentItems(): SuggestItem[] {
  const value = props.modelValue
  if (value === null) return []
  return Array.isArray(value) ? value : [value]
}

const selectedChips = computed(() =>
    currentItems().map((item) => ({key: itemKey(item), label: itemLabel(item)})),
)

const hasSelection = computed(() => selectedChips.value.length > 0)

function isSelected(item: SuggestItem): boolean {
  const key = itemKey(item)
  return currentItems().some((s) => itemKey(s) === key)
}

function toggle(item: SuggestItem): void {
  if (props.disabled) return
  if (props.multiple) {
    const key = itemKey(item)
    const current = currentItems()
    const next = current.some((s) => itemKey(s) === key)
        ? current.filter((s) => itemKey(s) !== key)
        : [...current, item]
    emit('update:modelValue', next)
  } else {
    emit('update:modelValue', isSelected(item) ? null : item)
    open.value = false
    search.value = ''
  }
}

function removeOne(key: string, e: MouseEvent): void {
  e.stopPropagation()
  if (props.disabled) return
  if (props.multiple) {
    emit('update:modelValue', currentItems().filter((s) => itemKey(s) !== key))
  } else {
    emit('update:modelValue', null)
  }
}

function clearAll(e: MouseEvent): void {
  e.stopPropagation()
  emit('update:modelValue', props.multiple ? [] : null)
}

async function loadItems(query: string): Promise<void> {
  if (!props.proxyUuid) return
  loading.value = true
  try {
    items.value = await suggestRepository.suggest(props.proxyUuid, query)
  } catch {
    items.value = []
  } finally {
    loading.value = false
  }
}

function onSearch(value: string): void {
  search.value = value
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => loadItems(value), 300)
}

async function onOpenChange(val: boolean): Promise<void> {
  open.value = val
  if (val) {
    if (search.value) loadItems(search.value)
    await nextTick()
    searchRef.value?.$el?.querySelector('input')?.focus()
  } else {
    search.value = ''
  }
}
</script>

<template>
  <Popover :open="open" @update:open="onOpenChange">
    <PopoverTrigger as-child>
      <button
          type="button"
          :disabled="disabled"
          class="flex min-h-9 w-full items-center gap-2 rounded-xl border px-3 py-1.5 text-left text-sm transition disabled:cursor-not-allowed disabled:opacity-50"
          :class="[
              error ? 'border-destructive' : 'border-input hover:border-slate-300',
              open ? (error ? 'border-destructive ring-1 ring-destructive/30' : 'border-ring ring-1 ring-ring') : '',
          ]"
      >
        <div class="flex min-w-0 flex-1 flex-wrap gap-1">
          <template v-if="hasSelection">
            <span
                v-for="chip in selectedChips"
                :key="chip.key"
                :title="chip.label"
                class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700"
            >
              <span class="max-w-[160px] truncate">{{ chip.label }}</span>
              <X
                  v-if="!disabled"
                  class="size-3 shrink-0 text-slate-400 transition hover:text-slate-700"
                  @click="removeOne(chip.key, $event)"
              />
            </span>
          </template>
          <span v-else class="text-muted-foreground">{{ placeholder || 'Начните вводить...' }}</span>
        </div>

        <span class="flex shrink-0 items-center gap-1 self-center">
          <X
              v-if="hasSelection && !disabled"
              class="size-3.5 text-muted-foreground transition hover:text-foreground"
              @click="clearAll"
          />
          <ChevronsUpDown class="size-3.5 text-muted-foreground"/>
        </span>
      </button>
    </PopoverTrigger>

    <PopoverContent
        align="start"
        :side-offset="4"
        class="w-[var(--reka-popover-trigger-width)] min-w-[200px] gap-0 p-0"
    >
      <div class="p-2 pb-1">
        <Input
            ref="searchRef"
            :model-value="search"
            :placeholder="placeholder || 'Поиск...'"
            class="h-8 text-sm"
            @update:model-value="onSearch(String($event))"
            @keydown.escape="onOpenChange(false)"
        />
      </div>

      <div v-if="loading" class="flex items-center justify-center gap-2 py-6 text-sm text-muted-foreground">
        <Loader2 class="size-4 animate-spin"/>
        Загрузка...
      </div>

      <div v-else-if="!search" class="py-6 text-center text-sm text-muted-foreground">
        Введите запрос для поиска
      </div>

      <div v-else-if="items.length === 0" class="py-6 text-center text-sm text-muted-foreground">
        Ничего не найдено
      </div>

      <div v-else class="max-h-60 overflow-y-auto py-1">
        <button
            v-for="(item, index) in items"
            :key="itemKey(item) || `suggest-${index}`"
            type="button"
            class="flex w-full items-center gap-2.5 px-3 py-2 text-sm transition hover:bg-accent"
            :class="isSelected(item) ? 'text-foreground font-medium' : 'text-foreground/80'"
            @click="toggle(item)"
        >
          <span
              class="flex size-4 shrink-0 items-center justify-center rounded-full border"
              :class="isSelected(item) ? 'border-primary' : 'border-border'"
          >
            <span v-if="isSelected(item)" class="size-2 rounded-full bg-primary"/>
          </span>
          <span class="min-w-0 truncate text-left">{{ itemLabel(item) }}</span>
          <Check v-if="multiple && isSelected(item)" class="ml-auto size-3.5 text-primary"/>
        </button>
      </div>
    </PopoverContent>
  </Popover>
</template>
