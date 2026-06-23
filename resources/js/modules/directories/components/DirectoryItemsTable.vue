<script setup lang="ts">
import {computed, nextTick, onMounted, ref, watch, type ComponentPublicInstance} from 'vue'
import {useVirtualizer} from '@tanstack/vue-virtual'
import {useDirectoryItems} from '@/modules/directories/composables/useDirectoryItems'
import {useHorizontalScrollArrows} from '@/composables/useHorizontalScrollArrows'
import {Button} from '@/components/ui/button'
import {
  Card, CardContent, CardHeader, CardTitle, CardDescription,
} from '@/components/ui/card'
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog'
import {Input} from '@/components/ui/input'
import {Label} from '@/components/ui/label'
import {NativeSelect} from '@/components/ui/native-select'
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table'
import {
  ArrowDown,
  ArrowUp,
  ArrowUpDown,
  Check,
  ChevronLeft,
  ChevronRight,
  Loader2,
  Pencil,
  Plus,
  Trash2
} from 'lucide-vue-next'
import {Skeleton} from '@/components/ui/skeleton'
import type {DirectorySchemaField} from '@/modules/directories/types/directory'
import DirectoryFilterBar from '@/modules/directories/components/DirectoryFilterBar.vue'
import {
  ContextMenuContent, ContextMenuItem, ContextMenuPortal, ContextMenuRoot,
  ContextMenuSeparator, ContextMenuTrigger,
} from 'reka-ui'

const props = defineProps<{
  directoryId: string
  versionId: number
  schemaFields: DirectorySchemaField[]
  canManage?: boolean
  canDelete?: boolean
  versionLabel?: string
  defaultSort?: string | null
}>()

const ctx = useDirectoryItems(props.directoryId, props.defaultSort, true)

// id синтетического «Другой» (mirrors DirectoryItemService::OTHER_ITEM_ID).
const OTHER_ITEM_ID = -1

function isOtherRow(id: number): boolean {
  return id === OTHER_ITEM_ID
}

// ── Виртуализация (TanStack Virtual, динамическая высота строк) ───────────────
const scrollParent = ref<HTMLElement | null>(null)
const totalColumns = computed(() => props.schemaFields.length + (props.canDelete ? 1 : 0))

// Горизонтальная прокрутка таблицы стрелками (общий composable).
const {
  canScrollLeft, canScrollRight, updateMetrics,
  onScroll: onTableScroll, startScroll, stopScroll,
} = useHorizontalScrollArrows(scrollParent)

// После загрузки/смены данных ширина таблицы меняется — пересчитываем,
// нужны ли стрелки.
async function refreshScrollMetrics(): Promise<void> {
  await nextTick()
  updateMetrics()
}

const rowVirtualizer = useVirtualizer(computed(() => ({
  count: ctx.flatTree.value.length,
  getScrollElement: () => scrollParent.value,
  estimateSize: () => 44, // стартовая оценка; реальная высота меряется measureElement
  overscan: 10,
  getItemKey: (index: number) => ctx.flatTree.value[index]?.id ?? index,
})))

// Реальный замер высоты строки (поддержка переноса/многострочных значений).
function measureElement(el: Element | ComponentPublicInstance | null): void {
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

onMounted(async () => {
  await ctx.loadItems(props.versionId)
  await refreshScrollMetrics()
})

watch(() => props.versionId, async (id) => {
  ctx.clearFilters()
  await ctx.loadItems(id)
  await refreshScrollMetrics()
})

// Колонки/переносы могут менять ширину при изменении набора строк.
watch(() => ctx.flatTree.value.length, () => void refreshScrollMetrics())

async function clearFiltersAndReload(): Promise<void> {
  ctx.clearFilters()
  await ctx.loadItems(props.versionId)
}

defineExpose({reload: () => ctx.loadItems(props.versionId), clearFiltersAndReload})
</script>

<template>
  <Card class="border-border/60">
    <CardHeader class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div class="space-y-1">
        <CardTitle>Элементы справочника</CardTitle>
        <CardDescription>
          {{ ctx.items.value.length }} записей
          <template v-if="versionLabel"> · {{ versionLabel }}</template>
        </CardDescription>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <Button variant="ghost" size="sm" class="h-8 text-xs text-muted-foreground" @click="ctx.expandAll()">
          Раскрыть все
        </Button>
        <Button variant="ghost" size="sm" class="h-8 text-xs text-muted-foreground" @click="ctx.collapseAll()">
          Свернуть
        </Button>
        <template v-if="canManage">
          <div class="h-4 w-px bg-border/60"/>
          <Button size="sm" class="gap-2" @click="ctx.openAddItem(schemaFields)">
            <Plus class="size-4"/>
            Добавить
          </Button>
        </template>
      </div>
    </CardHeader>

    <CardContent class="space-y-3">
      <!-- Bulk toolbar -->
      <div
          v-if="ctx.someSelected.value && canDelete"
          class="flex items-center gap-3 rounded-xl border border-primary/20 bg-primary/5 px-4 py-2.5"
      >
        <span class="text-sm font-medium">Выбрано: {{ ctx.selectedIds.value.size }}</span>
        <div class="ml-auto flex gap-2">
          <Button variant="outline" size="sm" class="h-7 text-xs" @click="ctx.selectedIds.value = new Set()">
            Снять выделение
          </Button>
          <Button variant="destructive" size="sm" class="h-7 gap-1.5 text-xs" @click="ctx.deleteSelected()">
            <Trash2 class="size-3"/>
            Удалить выбранные
          </Button>
        </div>
      </div>

      <DirectoryFilterBar
          :ctx="ctx"
          :searchable="schemaFields.some(f => f.searchable)"
          :filter-fields="schemaFields.filter(f => f.filterable)"
      />

      <div class="group/table relative">
        <div ref="scrollParent" class="max-h-[65vh] overflow-auto rounded-xl border border-border/60"
             @scroll="onTableScroll">
          <Table>
            <colgroup>
              <col v-if="canDelete" class="w-10"/>
              <col v-for="f in schemaFields" :key="f.key"/>
            </colgroup>
            <TableHeader class="sticky top-0 z-20 bg-background [&_tr]:bg-background">
              <TableRow>
                <TableHead v-if="canDelete"
                           class="sticky left-0 z-20 w-10 bg-background pr-0 shadow-[1px_0_0_rgba(148,163,184,0.25)]">
                  <button
                      type="button"
                      class="flex size-4 items-center justify-center rounded border transition"
                      :class="ctx.allSelected.value
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-border/60 hover:border-primary/60'"
                      @click="ctx.toggleSelectAll()"
                  >
                    <Check v-if="ctx.allSelected.value" class="size-2.5"/>
                  </button>
                </TableHead>
                <TableHead v-for="(f, fIdx) in schemaFields" :key="f.key" class="cursor-pointer select-none"
                           @click="ctx.toggleSort(f.key)">
                  <div class="inline-flex items-center gap-1 whitespace-normal break-words align-top"
                       style="min-width: 7.5rem; max-width: 15.625rem">
                    <div v-if="fIdx === 0" class="size-4 shrink-0"/>
                    <span>{{ f.name }}</span>
                    <ArrowUp v-if="ctx.sortKey.value === f.key && ctx.sortDir.value === 'asc'"
                             class="size-3 shrink-0 text-primary"/>
                    <ArrowDown v-else-if="ctx.sortKey.value === f.key && ctx.sortDir.value === 'desc'"
                               class="size-3 shrink-0 text-primary"/>
                    <ArrowUpDown v-else class="size-3 shrink-0 text-muted-foreground/40"/>
                  </div>
                </TableHead>
              </TableRow>
            </TableHeader>

            <TableBody>
              <template v-if="ctx.loading.value">
                <TableRow v-for="i in 8" :key="i">
                  <TableCell v-if="canDelete" class="pr-0">
                    <Skeleton class="size-4 rounded"/>
                  </TableCell>
                  <TableCell v-for="f in schemaFields" :key="f.key">
                    <Skeleton class="h-4" :style="{ width: `${48 + (i * 13 + f.sort_order * 7) % 80}px` }"/>
                  </TableCell>
                </TableRow>
              </template>
              <TableRow v-else-if="!ctx.flatTree.value.length">
                <TableCell :colspan="totalColumns" class="h-24 text-center text-sm text-muted-foreground">
                  Нет элементов.
                </TableCell>
              </TableRow>
              <template v-else>
                <tr v-if="paddingTop > 0" :style="{ height: `${paddingTop}px` }">
                  <td :colspan="totalColumns" class="p-0"/>
                </tr>
                <ContextMenuRoot
                    v-for="{ vRow, node } in virtualNodes"
                    :key="node.id"
                >
                  <ContextMenuTrigger as-child>
                    <tr
                        :ref="measureElement"
                        :data-index="vRow.index"
                        class="group/row border-b transition-colors hover:bg-muted/50"
                        :class="[ctx.selectedIds.value.has(node.id) ? 'bg-primary/5' : '', isOtherRow(node.id) ? 'bg-muted/30 text-muted-foreground italic' : '']"
                    >
                      <TableCell
                          v-if="canDelete"
                          class="sticky left-0 z-10 bg-background shadow-[1px_0_0_rgba(148,163,184,0.18)] transition-colors group-hover/row:bg-muted/50"
                      >
                        <button
                            v-if="!isOtherRow(node.id)"
                            type="button"
                            class="flex size-4 items-center justify-center rounded border transition"
                            :class="ctx.selectedIds.value.has(node.id)
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : 'border-border/60 hover:border-primary/60'"
                            @click="ctx.toggleSelect(node.id)"
                        >
                          <Check v-if="ctx.selectedIds.value.has(node.id)" class="size-2.5"/>
                        </button>
                      </TableCell>
                      <TableCell v-if="isOtherRow(node.id)" :colspan="schemaFields.length">
                        {{ String(Object.values(node.data)[0] ?? '') }}
                      </TableCell>
                      <TableCell v-for="(f, fIdx) in schemaFields" v-else :key="f.key">
                        <div class="inline-block whitespace-normal break-words align-top"
                             style="min-width: 7.5rem; max-width: 15.625rem">
                          <div
                              v-if="fIdx === 0"
                              class="flex items-center gap-1"
                              :style="{ paddingLeft: `${node.depth * 16}px` }"
                          >
                            <button
                                v-if="node.hasChildren"
                                type="button"
                                class="flex size-4 shrink-0 items-center justify-center text-muted-foreground transition hover:text-foreground"
                                @click="ctx.treeExpanded[node.id] = !ctx.treeExpanded[node.id]"
                            >
                              <svg
                                  class="size-3 transition-transform duration-150"
                                  :class="node.isExpanded ? 'rotate-90' : ''"
                                  viewBox="0 0 24 24" fill="none"
                                  stroke="currentColor" stroke-width="2"
                              >
                                <path d="m9 18 6-6-6-6"/>
                              </svg>
                            </button>
                            <div v-else class="size-4 shrink-0"/>
                            <span>{{ ctx.formatFieldValue(node, f) }}</span>
                          </div>
                          <template v-else>{{ ctx.formatFieldValue(node, f) }}</template>
                        </div>
                      </TableCell>
                    </tr>
                  </ContextMenuTrigger>
                  <ContextMenuPortal>
                    <ContextMenuContent
                        class="z-50 min-w-[140px] overflow-hidden rounded-lg border bg-popover p-1 text-popover-foreground shadow-md"
                    >
                      <ContextMenuItem
                          v-if="isOtherRow(node.id)"
                          class="flex cursor-default select-none items-center gap-2 rounded-md px-2 py-1.5 text-sm text-muted-foreground outline-none"
                      >
                        Вариант «Другой» — настраивается в версии
                      </ContextMenuItem>
                      <ContextMenuItem
                          v-if="canManage && !isOtherRow(node.id)"
                          class="flex cursor-default select-none items-center gap-2 rounded-md px-2 py-1.5 text-sm outline-none hover:bg-accent focus:bg-accent"
                          @click="ctx.openEditItem(node, schemaFields)"
                      >
                        <Pencil class="size-3.5 text-muted-foreground"/>
                        Редактировать
                      </ContextMenuItem>
                      <ContextMenuSeparator v-if="canManage && canDelete && !isOtherRow(node.id)"
                                            class="my-1 h-px bg-border"/>
                      <ContextMenuItem
                          v-if="canDelete && !isOtherRow(node.id)"
                          class="flex cursor-default select-none items-center gap-2 rounded-md px-2 py-1.5 text-sm text-destructive outline-none hover:bg-accent focus:bg-accent"
                          @click="ctx.removeItem(node)"
                      >
                        <Trash2 class="size-3.5"/>
                        Удалить
                      </ContextMenuItem>
                    </ContextMenuContent>
                  </ContextMenuPortal>
                </ContextMenuRoot>
                <tr v-if="paddingBottom > 0" :style="{ height: `${paddingBottom}px` }">
                  <td :colspan="totalColumns" class="p-0"/>
                </tr>
              </template>
            </TableBody>
          </Table>
        </div>

        <div
            v-if="canScrollLeft"
            class="absolute inset-y-0 left-7 z-20 flex w-10 items-center justify-start pl-1 opacity-0 transition-opacity group-hover/table:opacity-100 group-focus-within/table:opacity-100"
            @mouseenter="startScroll('left')"
            @mouseleave="stopScroll"
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
            v-if="canScrollRight"
            class="pointer-events-none absolute inset-y-0 right-5 z-20 flex w-12 items-center justify-end opacity-0 transition-opacity group-hover/table:opacity-100 group-focus-within/table:opacity-100"
            @mouseenter="startScroll('right')"
            @mouseleave="stopScroll"
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
    </CardContent>
  </Card>

  <!-- Edit / Add item dialog -->
  <Dialog v-model:open="ctx.editDialogOpen.value">
    <DialogContent class="flex max-h-[90vh] flex-col gap-0 p-0 sm:max-w-lg">
      <DialogHeader class="shrink-0 border-b border-border/60 px-6 py-4">
        <DialogTitle>{{ ctx.editingItemId.value ? 'Редактировать запись' : 'Новая запись' }}</DialogTitle>
        <DialogDescription>Заполните поля согласно схеме справочника.</DialogDescription>
      </DialogHeader>

      <div class="min-h-0 flex-1 overflow-y-auto px-6 py-4">
        <div v-if="ctx.editError.value"
             class="mb-4 rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
          {{ ctx.editError.value }}
        </div>
        <div class="grid gap-4">
          <div v-if="ctx.editingItemId.value" class="grid grid-cols-2 gap-3">
            <div class="space-y-1.5">
              <Label>ID</Label>
              <Input :model-value="String(ctx.editingItemId.value)" disabled class="font-mono"/>
            </div>
            <div class="space-y-1.5">
              <Label>External key</Label>
              <Input v-model="ctx.editingExternalKey.value" placeholder="Внешний ключ..." class="font-mono"/>
            </div>
          </div>
          <div v-else class="space-y-1.5">
            <Label>External key</Label>
            <Input v-model="ctx.editingExternalKey.value" placeholder="Внешний ключ..." class="font-mono"/>
          </div>
          <div v-for="f in schemaFields" :key="f.key" class="space-y-2">
            <Label :for="`ef-${f.key}`">{{ f.name }}</Label>
            <NativeSelect v-if="f.type === 'boolean'" :id="`ef-${f.key}`" v-model="ctx.editFields[f.key]">
              <option value="">— Не выбрано —</option>
              <option value="true">Да</option>
              <option value="false">Нет</option>
            </NativeSelect>
            <Input v-else-if="f.type === 'date'" :id="`ef-${f.key}`" v-model="ctx.editFields[f.key]" type="date"/>
            <Input v-else-if="f.type === 'datetime'" :id="`ef-${f.key}`" v-model="ctx.editFields[f.key]"
                   type="datetime-local"/>
            <Input v-else-if="f.type === 'integer'" :id="`ef-${f.key}`" v-model="ctx.editFields[f.key]" type="number"
                   step="1" :placeholder="`Значение ${f.name}`"/>
            <Input v-else :id="`ef-${f.key}`" v-model="ctx.editFields[f.key]" :placeholder="`Значение ${f.name}`"/>
          </div>
          <div class="space-y-2">
            <Label>Родительский элемент</Label>
            <select v-model="ctx.editingParentId.value"
                    class="flex h-10 w-full rounded-lg border border-input bg-background px-3 text-sm">
              <option :value="null">— Нет родителя (корневой) —</option>
              <option
                  v-for="item in ctx.items.value.filter(i => i.id !== ctx.editingItemId.value)"
                  :key="item.id"
                  :value="item.id"
              >
                #{{ item.id }} · {{ Object.values(item.data ?? {})[0] ?? `#${item.id}` }}
              </option>
            </select>
          </div>
        </div>
      </div>

      <DialogFooter class="shrink-0 border-t border-border/60 px-6 py-4">
        <Button variant="outline" @click="ctx.editDialogOpen.value = false">Отмена</Button>
        <Button :disabled="ctx.editSaving.value" @click="ctx.saveItem(schemaFields)">
          <Loader2 v-if="ctx.editSaving.value" class="mr-2 size-4 animate-spin"/>
          {{ ctx.editingItemId.value ? 'Сохранить' : 'Добавить' }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>

<style scoped>
/*
  shadcn <Table> оборачивает <table> в собственный контейнер с overflow-x-auto.
  Из-за этого горизонтальный скролл «съедается» внутренним контейнером и не
  доходит до scrollParent — стрелки прокрутки (и общий скролл по X) не работают.
  Отключаем внутренний overflow, чтобы оба направления скроллились на одном
  scrollParent — как в SurveyDirectoryTableField.
*/
:deep([data-slot='table-container']) {
  overflow-x: visible;
}
</style>
