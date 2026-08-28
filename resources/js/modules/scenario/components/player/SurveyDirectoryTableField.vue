<script setup lang="ts">
import {ref, computed, nextTick, watch, type ComponentPublicInstance} from 'vue'
import {useVirtualizer} from '@tanstack/vue-virtual'
import {useHorizontalScrollArrows} from '@/composables/useHorizontalScrollArrows'
import {
  Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle,
} from '@/components/ui/dialog'
import {Button} from '@/components/ui/button'
import {Skeleton} from '@/components/ui/skeleton'
import SurveyDirectoryTableTrigger from './SurveyDirectoryTableTrigger.vue'
import DirectoryFilterBar from '@/modules/directories/components/DirectoryFilterBar.vue'
import {useDirectoryItems} from '@/modules/directories/composables/useDirectoryItems'
import {directoryRepository} from '@/modules/directories/repositories/directoryRepository'
import {useExpressionLabelBatch} from '@/modules/expression/composables/useExpressionLabelBatch'
import {OTHER_ITEM_ID} from '@/lib/directory-list-shape'
import type {DirectoryItem, DirectorySchemaField} from '@/modules/directories/types/directory'
import {
  ArrowDown, ArrowUp, ArrowUpDown,
  Check, ChevronLeft, ChevronRight,
} from 'lucide-vue-next'

interface FieldConfig {
  key: string
  visible: boolean
  defaultValue: string
  filterable: boolean
  lockFilter?: boolean
  filterMode?: 'literal' | 'template'
  filterValues?: string[]
}

interface DirectoryItemValue {
  id: string
  label: string
  data: Record<string, string | null>
  parent_id: string | null
  external_key: string
}

type FieldValue = DirectoryItemValue | DirectoryItemValue[] | string | string[] | null

const props = withDefaults(defineProps<{
  modelValue: FieldValue
  directoryId: string
  versionId?: string
  labelTemplate?: string
  multiple?: boolean
  allowSelection?: boolean
  fields?: FieldConfig[]
  defaultSearch?: string
  disabled?: boolean
  error?: boolean
  context?: Record<string, unknown>
}>(), {
  versionId: '',
  labelTemplate: '',
  multiple: false,
  allowSelection: true,
  fields: () => [],
  defaultSearch: '',
  disabled: false,
  error: false,
  context: () => ({}),
})

const emit = defineEmits<{
  'update:modelValue': [value: DirectoryItemValue | DirectoryItemValue[] | '']
}>()

const open = ref(false)
const schema = ref<DirectorySchemaField[]>([])
const schemaReady = ref(false)
const expressionLabels = useExpressionLabelBatch()

const ctx = useDirectoryItems(props.directoryId, null, true)

const pendingItems = ref<Map<string, DirectoryItemValue>>(new Map())
const tableScrollRef = ref<HTMLElement | null>(null)
const tableRef = ref<HTMLTableElement | null>(null)

const colWidths = ref<Record<string, number>>({})

function measureColWidths(): void {
  const table = tableRef.value
  if (!table) return
  const headerRow = table.querySelector('thead tr')
  if (!headerRow) return
  const ths = Array.from(headerRow.querySelectorAll<HTMLElement>('th'))
  const next: Record<string, number> = {}
  let i = 0
  if (props.allowSelection) {
    next.__select = Math.ceil(ths[i++]?.getBoundingClientRect().width ?? 40)
  }
  for (const f of visibleSchemaFields.value) {
    next[f.key] = Math.ceil(ths[i++]?.getBoundingClientRect().width ?? 160)
  }
  colWidths.value = next
}

const {
  canScrollLeft: canScrollTableLeft,
  canScrollRight: canScrollTableRight,
  updateMetrics: updateTableScrollMetrics,
  onScroll: onTableScroll,
  startScroll: startTableHorizontalScroll,
  stopScroll: stopTableHorizontalScroll,
} = useHorizontalScrollArrows(tableScrollRef)

const rowVirtualizer = useVirtualizer(computed(() => {
  const scrollEl = tableScrollRef.value
  return {
    count: ctx.flatTree.value.length,
    getScrollElement: () => scrollEl,
    estimateSize: () => 41,
    overscan: 8,
    getItemKey: (index: number) => ctx.flatTree.value[index]?.id ?? index,
  }
}))

function measureRow(el: Element | ComponentPublicInstance | null): void {
  if (el instanceof Element) rowVirtualizer.value.measureElement(el)
}

const virtualRows = computed(() => rowVirtualizer.value.getVirtualItems())
const virtualNodes = computed(() =>
    virtualRows.value.map((vRow) => ({vRow, node: ctx.flatTree.value[vRow.index]!})),
)
const paddingTop = computed(() => (virtualRows.value.length ? virtualRows.value[0].start : 0))
const paddingBottom = computed(() =>
    virtualRows.value.length
        ? rowVirtualizer.value.getTotalSize() - virtualRows.value[virtualRows.value.length - 1].end
        : 0,
)

function getItemValue(item: DirectoryItem): string {
  return String(item.id)
}

function getItemLabel(item: DirectoryItem): string {
  if (item.id === OTHER_ITEM_ID) {
    return String(Object.values(item.data)[0] ?? '')
  }
  const rendered = expressionLabels.label(item.id)
  if (rendered) return rendered
  const firstKey = Object.keys(item.data)[0]
  return firstKey ? String(item.data[firstKey] ?? item.id) : String(item.id)
}

function toDirectoryItemValue(item: DirectoryItem): DirectoryItemValue {
  return {
    id: String(item.id),
    label: getItemLabel(item),
    data: item.data,
    parent_id: item.parent_id !== null && item.parent_id !== undefined ? String(item.parent_id) : null,
    external_key: item.external_key ?? '',
  }
}

function normalizeIncoming(value: FieldValue): DirectoryItemValue[] {
  if (value === null || value === undefined || value === '') return []
  const arr = Array.isArray(value) ? value : [value]
  const result: DirectoryItemValue[] = []
  for (const entry of arr) {
    if (typeof entry === 'string') {
      if (!entry) continue
      result.push({id: entry, label: entry, data: {}, parent_id: null, external_key: ''})
    } else if (entry && typeof entry === 'object' && 'id' in entry) {
      result.push({
        id: String(entry.id ?? ''),
        label: String(entry.label ?? entry.id ?? ''),
        data: (entry as DirectoryItemValue).data ?? {},
        parent_id: (entry as DirectoryItemValue).parent_id ?? null,
        external_key: (entry as DirectoryItemValue).external_key ?? '',
      })
    }
  }
  return result.filter((i) => i.id)
}

const currentItems = computed<DirectoryItemValue[]>(() => normalizeIncoming(props.modelValue))

const hasSelection = computed(() => currentItems.value.length > 0)

const selectionChips = computed(() =>
    currentItems.value.map((i) => {
      const fromTemplate = expressionLabels.label(i.id)
      return {value: i.id, label: fromTemplate || i.label || i.id}
    }),
)

watch([
  () => props.labelTemplate,
  () => props.context,
  currentItems,
], () => {
  void expressionLabels.load(
      props.labelTemplate,
      currentItems.value.map((item) => ({id: item.id, data: item.data})),
      props.context,
  )
}, {immediate: true})

const fieldConfigMap = computed(() =>
    Object.fromEntries((props.fields ?? []).map((f) => [f.key, f])),
)

const visibleSchemaFields = computed((): DirectorySchemaField[] => {
  if (props.fields.length === 0) return schema.value
  return schema.value.filter((sf) => fieldConfigMap.value[sf.key]?.visible !== false)
})

const tableColspan = computed(() => visibleSchemaFields.value.length + (props.allowSelection ? 1 : 0))

const baseItemsLoaded = ref(false)

async function ensureBaseItems(): Promise<void> {
  if (baseItemsLoaded.value) return
  baseItemsLoaded.value = true
  const savedSingle: Record<string, string> = {...ctx.activeFilters}
  const savedMulti: Record<string, string[]> = {...ctx.activeFiltersMulti}
  const savedTo: Record<string, string> = {...ctx.activeFiltersTo}
  ctx.clearFilters()
  const vId = props.versionId ? Number(props.versionId) : undefined
  await ctx.loadItems(vId)
  for (const [k, v] of Object.entries(savedSingle)) ctx.activeFilters[k] = v
  for (const [k, v] of Object.entries(savedMulti)) ctx.activeFiltersMulti[k] = v
  for (const [k, v] of Object.entries(savedTo)) ctx.activeFiltersTo[k] = v
}

function onFilterOpen(f: DirectorySchemaField): void {
  if (f.filter_type !== 'list') return
  void ensureBaseItems()
}

const filterableSchemaFields = computed((): DirectorySchemaField[] =>
    schema.value.filter((sf) => {
      const cfg = fieldConfigMap.value[sf.key]
      if (!cfg?.filterable) return false
      if (cfg.lockFilter) return false
      return true
    }),
)

const hasSearchable = computed(() => schema.value.some((sf) => sf.searchable))

async function loadSchema(): Promise<void> {
  if (schemaReady.value || !props.directoryId) return
  try {
    const res = await directoryRepository.versions(props.directoryId)
    const version = props.versionId
        ? res.items.find((v) => String(v.id) === String(props.versionId))
        : res.items.find((v) => v.is_active)
    schema.value = version?.schema_json ?? []
    schemaReady.value = true
  } catch { /* silent */
  }
}

async function onOpen(): Promise<void> {
  if (!props.directoryId) return

  open.value = true
  const map = new Map<string, DirectoryItemValue>()
  for (const i of currentItems.value) map.set(i.id, i)
  pendingItems.value = map

  await loadSchema()

  ctx.clearFilters()
  if (props.defaultSearch) ctx.searchQuery.value = props.defaultSearch

  for (const cfg of props.fields) {
    const values = cfg.filterValues ?? []
    if (cfg.filterMode === 'literal' && values.length > 0) {
      ctx.activeFiltersMulti[cfg.key] = [...values]
      continue
    }
    if (cfg.defaultValue && cfg.defaultValue.trim() !== '') {
      ctx.activeFilters[cfg.key] = cfg.defaultValue
    }
  }

  const vId = props.versionId ? Number(props.versionId) : undefined
  await ctx.loadItems(vId)
  colWidths.value = {}
  await nextTick()
  measureColWidths()
  if (tableScrollRef.value) {
    tableScrollRef.value.scrollTop = 0
    updateTableScrollMetrics()
  }
}

function onClose(): void {
  stopTableHorizontalScroll()
  open.value = false
}

function onConfirm(): void {
  const items = Array.from(pendingItems.value.values())
  if (props.multiple) {
    emit('update:modelValue', items)
  } else {
    emit('update:modelValue', items[0] ?? '')
  }
  open.value = false
}

function clearSelection(e: MouseEvent): void {
  e.stopPropagation()
  if (props.disabled) return
  emit('update:modelValue', props.multiple ? [] : '')
}

function removeChip(value: string): void {
  if (props.disabled) return
  if (props.multiple) {
    emit('update:modelValue', currentItems.value.filter((i) => i.id !== value))
  } else {
    emit('update:modelValue', '')
  }
}

function isPending(item: DirectoryItem): boolean {
  return pendingItems.value.has(getItemValue(item))
}

const allRowValues = computed<string[]>(() => ctx.flatTree.value.map((i) => getItemValue(i)))

const allRowsSelected = computed<boolean>(() =>
    allRowValues.value.length > 0 && allRowValues.value.every((v) => pendingItems.value.has(v)),
)
const someRowsSelected = computed<boolean>(() =>
    !allRowsSelected.value && allRowValues.value.some((v) => pendingItems.value.has(v)),
)

function toggleAllRows(): void {
  if (!props.allowSelection || props.disabled || !props.multiple) return
  const next = new Map(pendingItems.value)
  if (allRowsSelected.value) {
    for (const node of ctx.flatTree.value) next.delete(getItemValue(node))
  } else {
    for (const node of ctx.flatTree.value) next.set(getItemValue(node), toDirectoryItemValue(node))
  }
  pendingItems.value = next
}

function toggleRow(item: DirectoryItem): void {
  if (!props.allowSelection || props.disabled) return
  const v = getItemValue(item)
  if (props.multiple) {
    const next = new Map(pendingItems.value)
    if (next.has(v)) next.delete(v)
    else next.set(v, toDirectoryItemValue(item))
    pendingItems.value = next
  } else {
    const was = isPending(item)
    emit('update:modelValue', was ? '' : toDirectoryItemValue(item))
    open.value = false
  }
}

const pendingCount = computed(() => pendingItems.value.size)
const totalRows = computed(() => ctx.flatTree.value.length)
</script>

<template>
  <SurveyDirectoryTableTrigger
      :chips="selectionChips"
      :has-selection="hasSelection"
      :directory-id="directoryId"
      :disabled="disabled"
      :error="error"
      @open="onOpen"
      @remove-chip="removeChip"
      @clear-selection="clearSelection"
  />

  <!-- Modal -->
  <Dialog :open="open" @update:open="(v: boolean) => { if (!v) onClose() }">
    <DialogContent
        class="flex max-h-[84vh] w-[min(1040px,94vw)] max-w-[min(1040px,94vw)] flex-col gap-0 overflow-hidden p-0 sm:max-w-[min(1040px,94vw)]">
      <DialogHeader class="shrink-0 border-b border-border/70 bg-background px-5 py-3.5">
        <DialogTitle class="text-base font-semibold">Выбор из справочника</DialogTitle>
        <DialogDescription class="sr-only">Выбор записей из справочника</DialogDescription>
      </DialogHeader>

      <!-- Body -->
      <div class="min-h-0 flex-1 space-y-3 overflow-y-auto bg-slate-50/40 p-4">
        <DirectoryFilterBar
            :ctx="ctx"
            :searchable="hasSearchable"
            :filter-fields="filterableSchemaFields"
            @filter-open="onFilterOpen"
        />

        <!-- Table -->
        <div class="group/table relative overflow-hidden rounded-lg border border-border/70 bg-background shadow-sm">
          <div
              ref="tableScrollRef"
              class="max-h-[52vh] overflow-auto"
              @scroll="onTableScroll"
          >
            <table
                ref="tableRef"
                class="w-full min-w-[720px] border-collapse text-sm"
                :style="Object.keys(colWidths).length ? { tableLayout: 'fixed' } : {}"
            >
              <colgroup v-if="Object.keys(colWidths).length">
                <col v-if="allowSelection" :style="{ width: `${colWidths.__select ?? 40}px` }"/>
                <col v-for="f in visibleSchemaFields" :key="f.key" :style="{ width: `${colWidths[f.key] ?? 160}px` }"/>
              </colgroup>
              <thead>
              <tr class="sticky top-0 z-20 border-b border-border/70 bg-slate-100/95 backdrop-blur">
                <th
                    v-if="allowSelection"
                    class="sticky left-0 z-20 w-10 bg-slate-100/95 px-3 py-2 shadow-[1px_0_0_rgba(148,163,184,0.25)]"
                    @click.stop
                >
                  <button
                      v-if="multiple"
                      type="button"
                      :title="allRowsSelected ? 'Снять все' : 'Выбрать все'"
                      class="flex size-4 items-center justify-center rounded border transition"
                      :class="allRowsSelected
                                                ? 'border-primary bg-primary text-primary-foreground shadow-sm'
                                                : someRowsSelected
                                                    ? 'border-primary bg-primary/30 text-primary-foreground'
                                                    : 'border-border bg-background hover:border-primary/40'"
                      :disabled="!allowSelection || disabled || !allRowValues.length"
                      @click.stop="toggleAllRows"
                  >
                    <Check v-if="allRowsSelected || someRowsSelected" class="size-3"/>
                  </button>
                </th>
                <th
                    v-for="f in visibleSchemaFields"
                    :key="f.key"
                    class="cursor-pointer select-none px-3 py-2 text-left text-[11px] font-semibold uppercase text-muted-foreground"
                    @click="ctx.toggleSort(f.key)"
                >
                  <div class="inline-flex items-center gap-1 whitespace-nowrap">
                    <span>{{ f.name }}</span>
                    <ArrowUp v-if="ctx.sortKey.value === f.key && ctx.sortDir.value === 'asc'"
                             class="size-3 shrink-0 text-primary"/>
                    <ArrowDown v-else-if="ctx.sortKey.value === f.key && ctx.sortDir.value === 'desc'"
                               class="size-3 shrink-0 text-primary"/>
                    <ArrowUpDown v-else class="size-3 shrink-0 text-muted-foreground/30"/>
                  </div>
                </th>
              </tr>
              </thead>
              <tbody class="divide-y divide-border/40">
              <!-- Loading skeletons -->
              <template v-if="ctx.loading.value">
                <tr v-for="i in 10" :key="i">
                  <td v-if="allowSelection" class="px-3 py-2.5">
                    <Skeleton class="size-3.5 rounded"/>
                  </td>
                  <td v-for="f in visibleSchemaFields" :key="f.key" class="px-3 py-2.5">
                    <Skeleton class="h-3" :style="{ width: `${48 + (i * 17 + f.sort_order * 11) % 90}px` }"/>
                  </td>
                </tr>
              </template>

              <!-- Data rows -->
              <template v-else-if="ctx.flatTree.value.length">
                <tr v-if="paddingTop > 0" aria-hidden="true">
                  <td :colspan="tableColspan" class="p-0" :style="{ height: `${paddingTop}px` }"/>
                </tr>
                <tr
                    v-for="{ vRow, node } in virtualNodes"
                    :key="node.id"
                    :ref="measureRow"
                    :data-index="vRow.index"
                    class="transition-colors odd:bg-background even:bg-slate-50/45"
                    :class="[
                                            allowSelection ? 'cursor-pointer hover:bg-slate-100' : '',
                                            isPending(node) ? '!bg-blue-100/60 hover:!bg-blue-100' : '',
                                        ]"
                    @click="toggleRow(node)"
                >
                  <td
                      v-if="allowSelection"
                      class="sticky left-0 z-10 w-10 bg-background px-3 py-2.5 align-middle shadow-[1px_0_0_rgba(148,163,184,0.18)]"
                      :class="isPending(node) ? '!bg-blue-100/60' : ''"
                      @click.stop="toggleRow(node)"
                  >
                                            <span
                                                v-if="multiple"
                                                class="flex size-4 items-center justify-center rounded border transition"
                                                :class="isPending(node) ? 'border-primary bg-primary text-primary-foreground shadow-sm' : 'border-border bg-background'"
                                            >
                                                <Check v-if="isPending(node)" class="size-3"/>
                                            </span>
                    <span
                        v-else
                        class="flex size-4 items-center justify-center rounded-full border transition"
                        :class="isPending(node) ? 'border-primary bg-primary/10' : 'border-border bg-background'"
                    >
                                                <span v-if="isPending(node)" class="size-2 rounded-full bg-primary"/>
                                            </span>
                  </td>
                  <td
                      v-if="node.id === OTHER_ITEM_ID"
                      :colspan="visibleSchemaFields.length"
                      class="px-3 py-2.5 align-middle italic text-foreground/90"
                  >
                    {{ getItemLabel(node) }}
                  </td>
                  <template v-else>
                    <td
                        v-for="(f, fIdx) in visibleSchemaFields"
                        :key="f.key"
                        class="px-3 py-2.5 align-top text-foreground/90"
                    >
                      <div
                          v-if="fIdx === 0"
                          class="flex items-center gap-1"
                          :style="{ paddingLeft: `${node.depth * 16}px` }"
                      >
                        <button
                            v-if="node.hasChildren"
                            type="button"
                            class="flex size-3.5 shrink-0 items-center justify-center text-muted-foreground transition hover:text-foreground"
                            @click.stop="ctx.treeExpanded[node.id] = !ctx.treeExpanded[node.id]"
                        >
                          <svg class="size-3 transition-transform duration-150"
                               :class="node.isExpanded ? 'rotate-90' : ''" viewBox="0 0 24 24" fill="none"
                               stroke="currentColor" stroke-width="2">
                            <path d="m9 18 6-6-6-6"/>
                          </svg>
                        </button>
                        <div v-else class="size-3.5 shrink-0"/>
                        <span class="max-w-[18rem] whitespace-pre-wrap break-words">{{
                            ctx.formatFieldValue(node, f)
                          }}</span>
                      </div>
                      <span v-else class="block max-w-[18rem] whitespace-pre-wrap break-words">{{
                          ctx.formatFieldValue(node, f)
                        }}</span>
                    </td>
                  </template>
                </tr>
                <tr v-if="paddingBottom > 0" aria-hidden="true">
                  <td :colspan="tableColspan" class="p-0" :style="{ height: `${paddingBottom}px` }"/>
                </tr>
              </template>

              <!-- Empty -->
              <tr v-else>
                <td
                    :colspan="tableColspan"
                    class="py-16 text-center text-sm text-muted-foreground"
                >
                  Нет элементов
                </td>
              </tr>
              </tbody>
            </table>
          </div>
          <div
              v-if="canScrollTableLeft"
              class="absolute inset-y-0 left-10 z-20 flex w-10 items-center justify-start to-transparent pl-1 opacity-0 transition-opacity group-hover/table:opacity-100 group-focus-within/table:opacity-100"
              @mouseenter="startTableHorizontalScroll('left')"
              @mouseleave="stopTableHorizontalScroll"
          >
            <button
                type="button"
                aria-label="Прокрутить таблицу влево"
                class="flex size-8 items-center justify-center rounded-full border border-border/60 bg-background/85 text-muted-foreground/80 shadow-sm backdrop-blur transition hover:bg-background hover:text-foreground"
            >
              <ChevronLeft class="size-3.5"/>
            </button>
          </div>
          <div
              v-if="canScrollTableRight"
              class="pointer-events-none absolute inset-y-0 right-4 z-20 flex w-12 items-center justify-end via-background/85 to-transparent opacity-0 transition-opacity group-hover/table:opacity-100 group-focus-within/table:opacity-100"
              @mouseenter="startTableHorizontalScroll('right')"
              @mouseleave="stopTableHorizontalScroll"
          >
            <button
                type="button"
                aria-label="Прокрутить таблицу вправо"
                class="pointer-events-auto flex size-8 items-center justify-center rounded-full border border-border/60 bg-background/85 text-muted-foreground/80 shadow-sm backdrop-blur transition hover:bg-background hover:text-foreground"
            >
              <ChevronRight class="size-4"/>
            </button>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="flex shrink-0 items-center justify-between gap-3 border-t border-border/70 bg-background px-5 py-3">
                <span class="min-w-0 truncate text-sm text-muted-foreground">
                    <template v-if="multiple && pendingCount > 0">Выбрано: {{ pendingCount }} · </template>
                    Записей: {{ totalRows }}
                </span>
        <div class="flex shrink-0 items-center gap-2">
          <Button variant="outline" @click="onClose">Отмена</Button>
          <Button v-if="multiple" :disabled="!allowSelection" @click="onConfirm">
            Применить
          </Button>
        </div>
      </div>
    </DialogContent>
  </Dialog>
</template>
