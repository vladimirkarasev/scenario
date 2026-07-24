<script setup lang="ts">
import {GripVertical, Settings, Trash2} from 'lucide-vue-next'
import TiptapTextEditor from '@/modules/scenario/components/tiptap/TiptapTextEditor.vue'
import type {BlockField} from '@/modules/scenario/lib/scenario-block-fields'
import type {Component} from 'vue'

const props = defineProps<{
  field: BlockField
  index: number
  canEdit: boolean
  isDragOver: boolean
  fieldTypeLabel: string
  fieldTypeIcon: Component
  actionName?: string | null
}>()

const emit = defineEmits<{
  dragstart: []
  dragenter: []
  drop: []
  dragend: []
  openSettings: []
  delete: []
  'update:value': [unknown]
}>()

function displayLabel(): string {
  if (props.field.type === 'action') {
    return props.actionName ?? 'Не выбрано'
  }
  if (props.field.type === 'hidden') {
    return props.field.name || `Поле ${props.index + 1}`
  }
  return props.field.label || `Поле ${props.index + 1}`
}

function isActionUnselected(): boolean {
  return props.field.type === 'action' && !('actionId' in props.field && props.field.actionId)
}
</script>

<template>
  <div
      class="rounded-2xl border border-slate-200 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.06)] transition-all"
      :class="isDragOver ? 'ring-2 ring-blue-300 ring-offset-2' : ''"
      @dragenter.prevent="emit('dragenter')"
      @dragover.prevent
      @drop.prevent="emit('drop')"
  >
    <!-- Header -->
    <div class="flex items-center gap-2 px-3 py-2.5">
      <button
          type="button"
          draggable="true"
          class="cursor-grab rounded-md p-1 text-slate-300 transition hover:bg-slate-100 hover:text-slate-500 active:cursor-grabbing disabled:pointer-events-none"
          :disabled="!canEdit"
          @dragstart="emit('dragstart')"
          @dragend="emit('dragend')"
      >
        <GripVertical class="size-3.5"/>
      </button>

      <div class="flex h-5 w-5 flex-none items-center justify-center rounded-md bg-slate-100">
        <component :is="fieldTypeIcon" class="size-3 text-slate-500"/>
      </div>

      <div class="min-w-0 flex-1">
                <span
                    class="text-[13px] font-medium"
                    :class="isActionUnselected() ? 'text-slate-400 italic' : 'text-slate-800'"
                >
                    {{ displayLabel() }}
                </span>
        <span
            v-if="field.type === 'action_list' && 'actions' in field && field.actions?.length"
            class="rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold tabular-nums text-amber-600"
        >{{ field.actions.length }}</span>
        <span
            v-if="field.varName && field.type !== 'collapse' && field.type !== 'rich_text' && field.type !== 'action'"
            class="ml-2 font-mono text-[10px] text-slate-400"
        >.{{ field.varName }}</span>
      </div>

      <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-500">
                {{ fieldTypeLabel }}
            </span>

      <span v-if="field.required" class="shrink-0 text-[10px] font-bold text-red-400">обяз.</span>

      <button
          v-if="canEdit"
          type="button"
          class="shrink-0 rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
          title="Настройки поля"
          @click="emit('openSettings')"
      >
        <Settings class="size-3.5"/>
      </button>

      <button
          type="button"
          class="shrink-0 rounded-lg p-1.5 text-slate-300 transition hover:bg-red-50 hover:text-red-500 disabled:pointer-events-none"
          :disabled="!canEdit"
          title="Удалить поле"
          @click="emit('delete')"
      >
        <Trash2 class="size-3.5"/>
      </button>
    </div>

    <!-- Rich text content -->
    <div v-if="field.type === 'rich_text'" class="border-t border-slate-100 px-3 pb-3 pt-2">
      <TiptapTextEditor
          :model-value="field.value"
          placeholder="Контент, который будет выведен в опросе..."
          :editable="canEdit"
          min-height="min-h-20"
          @update:model-value="emit('update:value', $event)"
      />
    </div>

    <!-- Collapse content -->
    <div v-if="field.type === 'collapse'" class="border-t border-slate-100 px-3 pb-3 pt-2">
      <div class="mb-2 text-[10px] font-bold uppercase tracking-wide text-slate-400">Содержимое</div>
      <TiptapTextEditor
          :model-value="field.value"
          placeholder="Содержимое блока..."
          :editable="canEdit"
          min-height="min-h-20"
          @update:model-value="emit('update:value', $event)"
      />
    </div>
  </div>
</template>
