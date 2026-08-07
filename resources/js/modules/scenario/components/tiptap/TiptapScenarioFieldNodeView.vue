<script setup lang="ts">
import {computed, nextTick, watch} from 'vue'
import {NodeViewContent, NodeViewWrapper} from '@tiptap/vue-3'
import {GripVertical, Settings, Trash2} from 'lucide-vue-next'
import TiptapTextEditor from '@/modules/scenario/components/tiptap/TiptapTextEditor.vue'
import {ContextMenu, ContextMenuContent, ContextMenuItem, ContextMenuTrigger} from '@/components/ui/context-menu'
import type {BlockField} from '@/modules/scenario/lib/scenario-block-fields'
import type {ScenarioFieldOptions} from '@/lib/tiptap-scenario-field'
import type {NodeViewProps} from '@tiptap/core'

const props = defineProps<NodeViewProps>()

const fieldId = computed(() => String(props.node.attrs.fieldId ?? ''))
const options = computed(() => props.extension.options as ScenarioFieldOptions)

const field = computed<BlockField | null>(() => options.value.getField(fieldId.value))
const canEdit = computed(() => options.value.isEditable())
const hasLabel = computed(() => Boolean(field.value?.label?.trim()))
const hideLabel = computed(() => Boolean(props.node.attrs.hideLabel))

watch(field, (value) => {
  if (value) return

  // Только что вставленная нода: свежесозданное поле ещё не долетело в props.fields
  // (реактивность родителя асинхронна) — даём один тик, прежде чем считать ноду сиротой.
  nextTick(() => {
    if (!options.value.getField(fieldId.value)) props.deleteNode()
  })
}, {immediate: true})

function onValueUpdate(value: unknown) {
  options.value.onUpdateField(fieldId.value, {value} as Partial<BlockField>)
}

function openFieldSettings() {
  if (!canEdit.value) return
  options.value.onOpenSettings(fieldId.value)
}

function deleteField() {
  if (!canEdit.value) return
  options.value.onDeleteField(fieldId.value)
}
</script>

<template>
  <NodeViewWrapper
      v-if="field"
      as="div"
      class="tiptap-scenario-field-node group relative my-3 rounded-lg"
      :data-field-id="fieldId"
  >
  <ContextMenu>
  <ContextMenuTrigger as-child :disabled="!canEdit">
  <div>
    <span
        data-drag-handle
        class="absolute top-0.5 -left-5 hidden size-4 cursor-grab items-center justify-center text-slate-300 transition hover:text-slate-500 group-hover:flex active:cursor-grabbing"
        title="Перетащить"
    >
      <GripVertical class="size-3.5" />
    </span>

    <div
        v-if="field.type !== 'hidden'"
        v-show="!hideLabel"
        class="relative mb-1 min-h-[1em] text-[11px] font-semibold uppercase tracking-wide text-slate-500 outline-none"
    >
      <span
          v-if="!hasLabel"
          contenteditable="false"
          class="pointer-events-none absolute inset-0 font-normal normal-case text-slate-300"
      >Название поля</span>
      <NodeViewContent as="span" class="relative block min-h-[1em]" />
    </div>

    <!-- rich_text/collapse: значение — это реальный контент, редактируется прямо здесь.
         contenteditable="false" на обёртке обязателен: без него это вложенный
         редактор внутри contentEditable-области внешнего документа, и браузер
         отдаёт фокус/ввод внешнему ProseMirror вместо вложенного. -->
    <div v-if="field.type === 'rich_text' || field.type === 'collapse'" contenteditable="false">
      <TiptapTextEditor
          :model-value="field.value"
          placeholder="Контент, который будет выведен в опросе..."
          :editable="canEdit"
          min-height="min-h-16"
          @update:model-value="onValueUpdate"
      />
    </div>

    <div
        v-else
        contenteditable="false"
        class="group/field relative flex h-9 items-center gap-2 rounded-md border border-slate-300 bg-white px-3 text-left shadow-xs transition"
        :class="canEdit ? 'cursor-pointer hover:border-blue-400 focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100' : ''"
        :role="canEdit ? 'button' : undefined"
        :tabindex="canEdit ? 0 : -1"
        @click="openFieldSettings"
        @keydown.enter.prevent="openFieldSettings"
        @keydown.space.prevent="openFieldSettings"
    >
      <span class="min-w-0 flex-1 truncate text-[13px] text-slate-500">
        Тип поля: <span class="font-medium text-slate-700">{{ options.fieldTypeLabel(field.type) }}</span>
      </span>

      <code
          v-if="field.varName"
          class="max-w-64 truncate rounded bg-slate-100 px-2 py-1 font-mono text-[11px] text-slate-500"
      >&#123;&#123; {{ field.varName }} &#125;&#125;</code>

      <Settings
          v-if="canEdit"
          class="size-3.5 shrink-0 text-slate-300 transition group-hover/field:text-blue-500"
      />
    </div>
  </div>
  </ContextMenuTrigger>
  <ContextMenuContent class="w-48">
    <ContextMenuItem @select="openFieldSettings">
      <Settings class="mr-2 size-4" />
      Настройки
    </ContextMenuItem>
    <ContextMenuItem class="text-red-600 focus:bg-red-50 focus:text-red-600" @select="deleteField">
      <Trash2 class="mr-2 size-4" />
      Удалить поле
    </ContextMenuItem>
  </ContextMenuContent>
  </ContextMenu>
  </NodeViewWrapper>
</template>
