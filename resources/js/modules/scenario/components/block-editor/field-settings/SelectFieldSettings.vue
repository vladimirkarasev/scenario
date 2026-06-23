<script lang="ts">
import {markRaw} from 'vue'
import {ListTree} from 'lucide-vue-next'

export const fieldMeta = {type: 'select', label: 'Список', icon: markRaw(ListTree)}
</script>

<script setup lang="ts">
import {computed, ref} from 'vue'
import {toast} from 'vue-sonner'
import {Input} from '@/components/ui/input'
import {Label} from '@/components/ui/label'
import {NativeSelect, NativeSelectOption} from '@/components/ui/native-select'
import {GripVertical, Plus, Trash2} from 'lucide-vue-next'
import type {SelectBlockField, SelectBlockFieldOption} from '../../../lib/scenario-block-fields'

const props = defineProps<{ field: SelectBlockField; disabled?: boolean }>()
const emit = defineEmits<{
  update: [patch: Partial<SelectBlockField>]
  'add-option': []
  'update-option': [change: { id: string; changes: Partial<SelectBlockFieldOption> }]
  'remove-option': [id: string]
  'reorder-options': [options: SelectBlockFieldOption[]]
}>()
defineOptions({inheritAttrs: false})
const hasGroups = computed(() => props.field?.options?.some((o) => o.parentId))

// Depth in the tree (0 = root, 1 = child, 2 = grandchild, …)
function getDepth(option: SelectBlockFieldOption, options: SelectBlockFieldOption[]): number {
  let depth = 0
  let current: SelectBlockFieldOption | undefined = option
  const visited = new Set()
  while (current?.parentId && !visited.has(current.id)) {
    const parentId: string = current.parentId
    visited.add(current.id)
    depth++
    current = options.find((o) => o.id === parentId)
    if (!current) {
      break
    }
  }
  return depth
}

// Collect the item + its entire subtree (all descendants)
function collectSubtree(fromId: string, options: SelectBlockFieldOption[]): SelectBlockFieldOption[] {
  const ids = new Set([fromId])
  let changed = true
  while (changed) {
    changed = false
    for (const o of options) {
      if (!ids.has(o.id) && o.parentId && ids.has(o.parentId)) {
        ids.add(o.id)
        changed = true
      }
    }
  }
  return options.filter((o) => ids.has(o.id))
}

// Returns true if making `fromId` a child of `targetId` would create a cycle
function wouldCreateCycle(fromId: string, targetId: string, options: SelectBlockFieldOption[]): boolean {
  const subtreeIds = new Set(collectSubtree(fromId, options).map((o) => o.id))
  return subtreeIds.has(targetId)
}

const canDrag = ref(false)
const draggingId = ref<string | null>(null)
const dragOverId = ref<string | null>(null)
const dragZone = ref<'before' | 'after' | 'inside' | null>(null)

const erroredOptionIds = ref<string[]>([])

function handleAddOption(): void {
  const options = props.field?.options ?? []
  const incompleteIds = options
      .filter((o) => !o.label?.trim() || !o.value?.trim())
      .map((o) => o.id)
  if (incompleteIds.length) {
    toast.warning('Заполните название и value у всех вариантов перед добавлением нового.')
    erroredOptionIds.value = incompleteIds
    setTimeout(() => {
      erroredOptionIds.value = []
    }, 2000)
    return
  }
  emit('add-option')
}

function reset(): void {
  draggingId.value = null
  dragOverId.value = null
  dragZone.value = null
}

function calcZone(e: DragEvent): 'before' | 'after' | 'inside' {
  if (!(e.currentTarget instanceof HTMLElement)) {
    return 'inside'
  }

  const rect = e.currentTarget.getBoundingClientRect()
  const y = e.clientY - rect.top
  const third = rect.height / 3
  if (y < third) return 'before'
  if (y > rect.height - third) return 'after'
  return 'inside'
}

function onDragStart(e: DragEvent, id: string): void {
  if (!canDrag.value) {
    e.preventDefault();
    return
  }
  draggingId.value = id
  if (e.dataTransfer) {
    e.dataTransfer.effectAllowed = 'move'
  }
}

function checkedFromEvent(event: Event): boolean {
  return event.target instanceof HTMLInputElement ? event.target.checked : false
}

function onDragOver(e: DragEvent, id: string): void {
  e.preventDefault()
  dragOverId.value = id
  dragZone.value = calcZone(e)
}

function onDrop(e: DragEvent, targetId: string, options: SelectBlockFieldOption[]): void {
  e.preventDefault()
  const fromId = draggingId.value
  const zone = dragZone.value
  reset()

  if (!fromId || fromId === targetId) return

  const draggedItem = options.find((o) => o.id === fromId)
  const targetItem = options.find((o) => o.id === targetId)
  if (!draggedItem || !targetItem) return

  if (zone === 'inside') {
    if (wouldCreateCycle(fromId, targetId, options)) return

    // Move entire subtree into target; insert after target's existing subtree
    const itemsToMove = collectSubtree(fromId, options).map((o) =>
        o.id === fromId ? {...o, parentId: targetId} : o,
    )
    const movedIds = new Set(itemsToMove.map((o) => o.id))
    const list = options.filter((o) => !movedIds.has(o.id))

    const targetSubtreeIds = new Set(collectSubtree(targetId, list).map((o) => o.id))
    let insertAt = list.findIndex((o) => o.id === targetId)
    for (let i = insertAt + 1; i < list.length; i++) {
      if (targetSubtreeIds.has(list[i].id)) insertAt = i
      else break
    }
    list.splice(insertAt + 1, 0, ...itemsToMove)
    emit('reorder-options', list)
    return
  }

  // Adopt the target's level; prevent cycles when reparenting into a subtree
  const newParentId = targetItem.parentId
  if (newParentId && wouldCreateCycle(fromId, newParentId, options)) return

  // Move entire subtree; only the root item's parentId changes
  const itemsToMove = collectSubtree(fromId, options).map((o) =>
      o.id === fromId ? {...o, parentId: newParentId} : o,
  )
  const movedIds = new Set(itemsToMove.map((o) => o.id))
  const remaining = options.filter((o) => !movedIds.has(o.id))

  let insertAt = remaining.findIndex((o) => o.id === targetId)

  if (zone === 'after') {
    // Skip past ALL descendants of the target (not just direct children)
    const targetSubtreeIds = new Set(collectSubtree(targetId, remaining).map((o) => o.id))
    let lastIdx = insertAt
    for (let i = insertAt + 1; i < remaining.length; i++) {
      if (targetSubtreeIds.has(remaining[i].id)) lastIdx = i
      else break
    }
    insertAt = lastIdx + 1
  }
  // zone === 'before': insertAt stays, item lands before the target

  remaining.splice(insertAt, 0, ...itemsToMove)
  emit('reorder-options', remaining)
}

function onDragEnd(): void {
  canDrag.value = false
  reset()
}

function rowDragClass(optionId: string): string {
  if (dragOverId.value !== optionId || draggingId.value === optionId) {
    return 'bg-white border-b border-slate-100 last:border-b-0'
  }
  if (dragZone.value === 'inside') return 'bg-indigo-50 border-b border-slate-100 ring-1 ring-inset ring-indigo-300'
  if (dragZone.value === 'before') return 'bg-white border-b border-slate-100 pt-8'
  return 'bg-white border-b border-slate-100 pb-8'
}
</script>

<template>
  <div class="space-y-1.5">
    <label class="flex h-9 items-center gap-2.5 rounded-xl border border-slate-200 bg-slate-50 px-3">
      <input
          :checked="Boolean(field.multiple)"
          type="checkbox"
          class="size-3.5 rounded border-slate-300"
          :disabled="disabled"
          @change="emit('update', { multiple: checkedFromEvent($event), value: checkedFromEvent($event) ? [] : '' })"
      />
      <span class="text-xs text-slate-700">Мультивыбор</span>
    </label>
    <label class="flex h-9 items-center gap-2.5 rounded-xl border border-slate-200 bg-slate-50 px-3">
      <input
          :checked="Boolean(field.allowRootSelection)"
          type="checkbox"
          class="size-3.5 rounded border-slate-300"
          :disabled="disabled"
          @change="emit('update', { allowRootSelection: checkedFromEvent($event) })"
      />
      <span class="text-xs text-slate-700">Можно выбрать корень</span>
    </label>
  </div>

  <div class="space-y-1.5">
    <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Поиск по умолчанию</Label>
    <Input
        :model-value="field.defaultSearch"
        placeholder="Значение для поиска при загрузке"
        class="h-8 text-sm"
        :disabled="disabled"
        @update:model-value="emit('update', { defaultSearch: String($event) })"
    />
  </div>

  <div class="space-y-1.5">
    <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">По умолчанию</Label>
    <Input
        :model-value="Array.isArray(field.value) ? field.value.join(', ') : field.value"
        placeholder="value или value1, value2"
        class="h-8 text-sm"
        :disabled="disabled"
        @update:model-value="emit('update', { value: field.multiple ? String($event).split(',').map((i) => i.trim()).filter(Boolean) : $event })"
    />
  </div>

  <div class="space-y-2">
    <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Варианты</Label>

    <div class="overflow-hidden rounded-xl border border-slate-200" @dragover.prevent>
      <!-- table header — only when no groups -->
      <div v-if="!hasGroups"
           class="grid grid-cols-[20px_1fr_1fr_160px_32px] gap-2 border-b border-slate-100 bg-slate-50 px-3 py-1.5">
        <span/>
        <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Название</span>
        <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Value</span>
        <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Родитель</span>
        <span/>
      </div>

      <div
          v-for="option in field.options"
          :key="option.id"
          :draggable="!disabled"
          class="relative items-center gap-2 px-3 py-2 transition-all"
          :class="[
                    hasGroups ? 'flex' : 'grid grid-cols-[20px_1fr_1fr_160px_32px]',
                    rowDragClass(option.id),
                ]"
          @dragstart="onDragStart($event, option.id)"
          @dragover="onDragOver($event, option.id)"
          @drop="onDrop($event, option.id, field.options)"
          @dragend="onDragEnd"
      >
        <div
            v-if="dragOverId === option.id && dragZone === 'before' && draggingId !== option.id"
            class="pointer-events-none absolute inset-x-3 top-2 flex items-center gap-1.5"
        >
          <div class="size-2 shrink-0 rounded-full bg-blue-500"/>
          <div class="h-0.5 flex-1 rounded-full bg-blue-400"/>
        </div>
        <div
            v-if="dragOverId === option.id && dragZone === 'after' && draggingId !== option.id"
            class="pointer-events-none absolute inset-x-3 bottom-2 flex items-center gap-1.5"
        >
          <div class="size-2 shrink-0 rounded-full bg-blue-500"/>
          <div class="h-0.5 flex-1 rounded-full bg-blue-400"/>
        </div>
        <!-- indent spacer proportional to tree depth -->
        <div
            v-if="hasGroups && option.parentId"
            class="shrink-0"
            :style="{ width: getDepth(option, field.options) * 20 + 'px' }"
        />

        <GripVertical
            class="size-4 shrink-0 cursor-grab text-slate-300 active:cursor-grabbing"
            @mousedown="canDrag = true"
            @mouseup="canDrag = false"
        />

        <Input
            :model-value="option.label"
            placeholder="Название"
            :class="['h-7 text-sm transition-colors', hasGroups ? 'min-w-0 flex-1' : '', erroredOptionIds.includes(option.id) && !option.label?.trim() ? '!border-red-400 !bg-red-50 placeholder:text-red-300' : '']"
            :disabled="disabled"
            @update:model-value="emit('update-option', { id: option.id, changes: { label: $event } })"
        />

        <Input
            :model-value="option.value"
            placeholder="value"
            :class="['h-7 font-mono text-sm transition-colors', hasGroups ? 'min-w-0 flex-1' : '', erroredOptionIds.includes(option.id) && !option.value?.trim() ? '!border-red-400 !bg-red-50 placeholder:text-red-300' : '']"
            :disabled="disabled"
            @update:model-value="emit('update-option', { id: option.id, changes: { value: $event } })"
        />

        <div :class="hasGroups ? 'w-32 shrink-0' : 'min-w-0 w-full'">
          <NativeSelect
              :model-value="option.parentId ?? ''"
              size="sm"
              class="!w-full"
              :disabled="disabled"
              @update:model-value="emit('update-option', { id: option.id, changes: { parentId: $event || null } })"
          >
            <NativeSelectOption value="">Корень</NativeSelectOption>
            <NativeSelectOption
                v-for="parent in field.options.filter((i) => i.id !== option.id)"
                :key="parent.id"
                :value="parent.id"
            >
              {{ parent.label || parent.value || parent.id }}
            </NativeSelectOption>
          </NativeSelect>
        </div>

        <button
            type="button"
            class="flex size-7 items-center justify-center rounded-lg text-slate-300 transition hover:bg-red-50 hover:text-red-500 disabled:pointer-events-none disabled:opacity-40"
            :disabled="field.options.length <= 1 || disabled"
            @click="emit('remove-option', option.id)"
        >
          <Trash2 class="size-3.5"/>
        </button>
      </div>
    </div>

    <button
        type="button"
        class="inline-flex w-full items-center justify-center gap-1.5 rounded-xl border border-dashed border-slate-200 py-2 text-[12px] font-medium text-slate-500 transition hover:border-blue-300 hover:bg-blue-50 hover:text-blue-600 disabled:pointer-events-none disabled:opacity-40"
        :disabled="disabled"
        @click="handleAddOption"
    >
      <Plus class="size-3.5"/>
      Добавить вариант
    </button>
  </div>
</template>
