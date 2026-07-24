<script setup lang="ts">
import {ref, computed, watch, nextTick, onMounted} from 'vue'
import {Popover, PopoverContent, PopoverTrigger} from '@/components/ui/popover'
import {Input} from '@/components/ui/input'
import {directoryRepository} from '@/modules/directories/repositories/directoryRepository'
import {renderLabelTemplate, extractTemplateKeys} from '@/modules/scenario/lib/directory-template'
import type {DirectoryItem} from '@/modules/directories/types/directory'
import type {DirectoryListShape} from '@/lib/directory-list-shape'
import {isDirectoryListShape, OTHER_ITEM_ID} from '@/lib/directory-list-shape'
import {ChevronsUpDown, X, Check, Loader2} from 'lucide-vue-next'

type IncomingModelValue =
    | DirectoryListShape
    | DirectoryListShape[]
    | string
    | string[]
    | null

interface FieldConfig {
  key: string
  defaultValue: string
  filterMode?: 'literal' | 'template'
  filterValues?: string[]
}

const props = withDefaults(defineProps<{
  modelValue: IncomingModelValue
  directoryId: string
  versionId?: string
  labelTemplate?: string
  multiple?: boolean
  allowRootSelection?: boolean
  fields?: FieldConfig[]
  defaultSearch?: string
  disabled?: boolean
  error?: boolean
  context?: Record<string, unknown>
  filterKey?: string
  filterValue?: string | null
}>(), {
  versionId: '',
  labelTemplate: '',
  multiple: false,
  allowRootSelection: true,
  fields: () => [],
  defaultSearch: '',
  disabled: false,
  error: false,
  context: () => ({}),
  filterKey: '',
  filterValue: null,
})

const emit = defineEmits<{
  'update:modelValue': [value: DirectoryListShape | DirectoryListShape[] | null]
}>()

const open = ref(false)
const search = ref('')
const searchRef = ref<InstanceType<typeof Input> | null>(null)
const items = ref<DirectoryItem[]>([])
const loading = ref(false)
let searchTimer: ReturnType<typeof setTimeout> | null = null

const OTHER_SHAPE_ID = String(OTHER_ITEM_ID)
const otherText = ref('')
const otherDefaultLabel = ref('Другой')

function isOtherItem(item: DirectoryItem): boolean {
  return item.id === OTHER_ITEM_ID
}

function getLabel(item: DirectoryItem): string {
  if (isOtherItem(item)) {
    return String(Object.values(item.data)[0] ?? otherDefaultLabel.value)
  }
  const rendered = renderLabelTemplate(props.labelTemplate, item.data, props.context)
  if (rendered) return rendered
  const firstKey = Object.keys(item.data)[0]
  return firstKey ? String(item.data[firstKey] ?? item.id) : String(item.id)
}

function getValue(item: DirectoryItem): string {
  return String(item.id)
}

function toShape(item: DirectoryItem): DirectoryListShape {
  const isOther = isOtherItem(item)
  const text = isOther ? otherText.value.trim() : ''
  return {
    id: String(item.id),
    label: isOther ? (text || getLabel(item)) : getLabel(item),
    data: item.data,
    parent_id: item.parent_id !== null ? String(item.parent_id) : null,
    external_key: item.external_key,
    ...(isOther ? {other_text: text || null} : {}),
  }
}

function modelToIds(value: IncomingModelValue): string[] {
  if (value === null || value === undefined || value === '') return []
  const arr = Array.isArray(value) ? value : [value]
  return arr
      .map((v) => (isDirectoryListShape(v) ? v.id : typeof v === 'string' ? v : ''))
      .filter((v) => v !== '')
}

function labelFromModel(id: string): string | null {
  const value = props.modelValue
  if (value === null) return null
  const arr = Array.isArray(value) ? value : [value]
  for (const v of arr) {
    if (isDirectoryListShape(v) && v.id === id) return v.label
  }
  return null
}

const rootItems = computed(() =>
    props.multiple ? items.value.filter((i) => !i.parent_id) : [],
)

function childItems(parentId: number): DirectoryItem[] {
  return items.value.filter((i) => i.parent_id === parentId)
}

const showTree = computed(() => props.multiple && !search.value && rootItems.value.length > 0)

const selectedValues = computed<string[]>(() => modelToIds(props.modelValue))

const hasSelection = computed(() => selectedValues.value.length > 0)

const selectedChips = computed(() =>
    selectedValues.value.map((id) => {
      const fromModel = labelFromModel(id)
      if (fromModel) return {value: id, label: fromModel}
      const found = items.value.find((i) => getValue(i) === id)
      return {value: id, label: found ? getLabel(found) : id}
    }),
)

function isSelected(item: DirectoryItem): boolean {
  return selectedValues.value.includes(getValue(item))
}

function currentShapes(): DirectoryListShape[] {
  const value = props.modelValue
  if (value === null) return []
  const arr = Array.isArray(value) ? value : [value]
  const out: DirectoryListShape[] = []
  for (const v of arr) {
    if (isDirectoryListShape(v)) out.push(v)
    else if (typeof v === 'string' && v !== '') {
      const found = items.value.find((i) => getValue(i) === v)
      if (found) out.push(toShape(found))
    }
  }
  return out
}

const otherSelectedShape = computed<DirectoryListShape | null>(() => {
  const value = props.modelValue
  if (value === null) return null
  const arr = Array.isArray(value) ? value : [value]
  for (const v of arr) {
    if (isDirectoryListShape(v) && v.id === OTHER_SHAPE_ID) return v
  }
  return null
})

const hasOtherSelected = computed(() => otherSelectedShape.value !== null)

watch(otherSelectedShape, (shape) => {
  const next = shape?.other_text ?? ''
  if (otherText.value !== next) otherText.value = next
}, {immediate: true})

function onOtherTextInput(value: string): void {
  otherText.value = value
  const current = otherSelectedShape.value
  if (!current) return

  const text = value.trim()
  const updated: DirectoryListShape = {
    ...current,
    label: text || otherDefaultLabel.value,
    other_text: text || null,
  }

  if (props.multiple) {
    emit('update:modelValue', currentShapes().map((s) => (s.id === OTHER_SHAPE_ID ? updated : s)))
  } else {
    emit('update:modelValue', updated)
  }
}

function toggle(item: DirectoryItem): void {
  if (props.disabled) return
  const id = getValue(item)
  const shape = toShape(item)
  if (props.multiple) {
    const current = currentShapes()
    const idx = current.findIndex((s) => s.id === id)
    const next = idx > -1
        ? current.filter((s) => s.id !== id)
        : [...current, shape]
    emit('update:modelValue', next)
  } else {
    emit('update:modelValue', isSelected(item) ? null : shape)
    open.value = false
    search.value = ''
  }
}

function removeOne(value: string, e: MouseEvent): void {
  e.stopPropagation()
  if (props.disabled) return
  if (props.multiple) {
    emit('update:modelValue', currentShapes().filter((s) => s.id !== value))
  } else {
    emit('update:modelValue', null)
  }
}

function clearAll(e: MouseEvent): void {
  e.stopPropagation()
  emit('update:modelValue', props.multiple ? [] : null)
}

const isFilteredAndEmpty = computed(
    () => Boolean(props.filterKey) && (props.filterValue === null || props.filterValue === ''),
)

async function loadItems(q?: string): Promise<void> {
  if (!props.directoryId) return
  if (isFilteredAndEmpty.value) {
    items.value = []
    return
  }
  loading.value = true
  try {
    const qs = new URLSearchParams({'page[size]': '200'})
    qs.set('filter[with_other]', '1')
    const effectiveQ = q !== undefined ? q : props.defaultSearch
    if (effectiveQ) qs.set('filter[search]', effectiveQ)
    if (props.versionId) qs.set('filter[version_id]', props.versionId)
    if (props.filterKey && props.filterValue) qs.set(`filter[${props.filterKey}]`, props.filterValue)

    for (const cfg of props.fields) {
      const values = cfg.filterValues ?? []
      if (cfg.filterMode === 'literal' && values.length > 0) {
        values.forEach((v) => qs.append(`filter[${cfg.key}][]`, v))
        continue
      }
      if (cfg.defaultValue && cfg.defaultValue.trim() !== '') {
        qs.set(`filter[${cfg.key}]`, cfg.defaultValue)
      }
    }

    const dataFields = extractTemplateKeys(props.labelTemplate)
    if (dataFields.length > 0) qs.set('fields[items]', dataFields.join(','))

    const res = await directoryRepository.items(props.directoryId, qs)
    items.value = res.items

    const other = res.items.find(isOtherItem)
    if (other) otherDefaultLabel.value = getLabel(other)
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
    search.value = props.defaultSearch
    loadItems()
    await nextTick()
    searchRef.value?.$el?.querySelector('input')?.focus()
  } else {
    search.value = ''
  }
}

onMounted(() => {
  if (props.directoryId && props.defaultSearch) loadItems()
})

watch(() => props.directoryId, () => {
  if (open.value) loadItems()
})

watch(() => props.filterValue, (next, prev) => {
  if (next === prev) return
  emit('update:modelValue', props.multiple ? [] : null)
  items.value = []
  if (open.value) loadItems()
})
</script>

<template>
  <div class="space-y-2">
    <Popover :open="open" @update:open="onOpenChange">
      <PopoverTrigger as-child>
        <button
            type="button"
            :disabled="disabled || isFilteredAndEmpty"
            class="flex min-h-9 w-full items-center gap-2 rounded-xl border px-3 py-1.5 text-left text-sm transition disabled:cursor-not-allowed disabled:opacity-50"
            :class="[
                    error
                        ? 'border-destructive'
                        : 'border-input hover:border-slate-300',
                    open ? (error ? 'border-destructive ring-1 ring-destructive/30' : 'border-ring ring-1 ring-ring') : '',
                ]"
        >
          <div class="flex min-w-0 flex-1 flex-wrap gap-1">
            <template v-if="hasSelection">
                        <span
                            v-for="chip in selectedChips"
                            :key="chip.value"
                            :title="chip.label"
                            class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700"
                        >
                            <span class="max-w-[160px] truncate">{{ chip.label }}</span>
                            <X
                                v-if="!disabled"
                                class="size-3 shrink-0 text-slate-400 transition hover:text-slate-700"
                                @click="removeOne(chip.value, $event)"
                            />
                        </span>
            </template>
            <span v-else class="text-muted-foreground">Выберите...</span>
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
        <!-- Search -->
        <div class="p-2 pb-1">
          <Input
              ref="searchRef"
              :model-value="search"
              placeholder="Поиск..."
              class="h-8 text-sm"
              @update:model-value="onSearch($event)"
              @keydown.escape="onOpenChange(false)"
          />
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex items-center justify-center gap-2 py-6 text-sm text-muted-foreground">
          <Loader2 class="size-4 animate-spin"/>
          Загрузка...
        </div>

        <!-- Empty -->
        <div v-else-if="items.length === 0" class="py-6 text-center text-sm text-muted-foreground">
          Ничего не найдено
        </div>

        <!-- Tree list (multiple + no active search + has root items) -->
        <div v-else-if="showTree" class="max-h-72 overflow-y-auto py-1">
          <template v-for="root in rootItems" :key="root.id">
            <button
                type="button"
                class="flex w-full items-center gap-2.5 px-3 py-2 text-sm transition"
                :class="[
                            allowRootSelection ? 'hover:bg-accent cursor-pointer' : 'cursor-default opacity-60 pointer-events-none',
                            isSelected(root) ? 'text-foreground font-medium' : 'text-foreground/80',
                        ]"
                @click="allowRootSelection && toggle(root)"
            >
                        <span
                            class="flex size-4 shrink-0 items-center justify-center rounded border"
                            :class="isSelected(root) ? 'border-primary bg-primary text-primary-foreground' : 'border-border'"
                        >
                            <Check v-if="isSelected(root)" class="size-3"/>
                        </span>
              <span class="min-w-0 truncate text-left font-medium">{{ getLabel(root) }}</span>
            </button>
            <button
                v-for="child in childItems(root.id)"
                :key="child.id"
                type="button"
                class="flex w-full items-center gap-2.5 py-2 pr-3 pl-8 text-sm transition hover:bg-accent"
                :class="isSelected(child) ? 'text-foreground font-medium' : 'text-foreground/80'"
                @click="toggle(child)"
            >
                        <span
                            class="flex size-4 shrink-0 items-center justify-center rounded border"
                            :class="isSelected(child) ? 'border-primary bg-primary text-primary-foreground' : 'border-border'"
                        >
                            <Check v-if="isSelected(child)" class="size-3"/>
                        </span>
              <span class="min-w-0 truncate text-left">{{ getLabel(child) }}</span>
            </button>
          </template>
        </div>

        <!-- Flat list -->
        <div v-else class="max-h-60 overflow-y-auto py-1">
          <button
              v-for="item in items"
              :key="item.id"
              type="button"
              class="flex w-full items-center gap-2.5 px-3 py-2 text-sm transition hover:bg-accent"
              :class="isSelected(item) ? 'text-foreground font-medium' : 'text-foreground/80'"
              @click="toggle(item)"
          >
                    <span
                        v-if="multiple"
                        class="flex size-4 shrink-0 items-center justify-center rounded border"
                        :class="isSelected(item) ? 'border-primary bg-primary text-primary-foreground' : 'border-border'"
                    >
                        <Check v-if="isSelected(item)" class="size-3"/>
                    </span>
            <span
                v-else
                class="flex size-4 shrink-0 items-center justify-center rounded-full border"
                :class="isSelected(item) ? 'border-primary' : 'border-border'"
            >
                        <span v-if="isSelected(item)" class="size-2 rounded-full bg-primary"/>
                    </span>
            <span class="min-w-0 truncate text-left">{{ getLabel(item) }}</span>
          </button>
        </div>

        <!-- Multi footer -->
        <div
            v-if="multiple && selectedValues.length > 0"
            class="flex items-center justify-between border-t border-border/60 px-3 py-2"
        >
          <span class="text-xs text-muted-foreground">Выбрано: {{ selectedValues.length }}</span>
          <button
              type="button"
              class="text-xs text-muted-foreground transition hover:text-destructive"
              @click="emit('update:modelValue', [])"
          >
            Очистить
          </button>
        </div>
      </PopoverContent>
    </Popover>

    <Input
        v-if="hasOtherSelected"
        :model-value="otherText"
        placeholder="Уточните..."
        class="h-9 text-sm"
        :disabled="disabled"
        @update:model-value="onOtherTextInput(String($event))"
    />
  </div>
</template>
