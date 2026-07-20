<script setup lang="ts">
import {computed, watch} from 'vue'
import {NodeViewContent, NodeViewWrapper} from '@tiptap/vue-3'
import {GripVertical, Settings} from 'lucide-vue-next'
import TiptapTextEditor from '@/modules/scenario/components/tiptap/TiptapTextEditor.vue'
import {Input} from '@/components/ui/input'
import {Textarea} from '@/components/ui/textarea'
import {NativeSelect} from '@/components/ui/native-select'
import type {BlockField} from '@/modules/scenario/lib/scenario-block-fields'
import type {ScenarioFieldOptions} from '@/lib/tiptap-scenario-field'
import type {NodeViewProps} from '@tiptap/core'

const props = defineProps<NodeViewProps>()

const fieldId = computed(() => String(props.node.attrs.fieldId ?? ''))
const options = computed(() => props.extension.options as ScenarioFieldOptions)

const field = computed<BlockField | null>(() => options.value.getField(fieldId.value))
const canEdit = computed(() => options.value.isEditable())
const hasLabel = computed(() => Boolean(field.value?.label?.trim()))

// Реальный контрол поля в документе — превью, а не форма (см. openFieldSettings
// ниже), поэтому визуально выглядит как обычное поле, но не перехватывает
// клавиатуру/клики; общий набор атрибутов для всех вариантов контрола.
const inertProps = {class: 'pointer-events-none', tabindex: -1, readonly: true} as const

// Поле могло быть удалено из другого места (список полей слева) — убираем
// осиротевшую ноду из документа, чтобы не редактировать «призрак».
watch(field, (value) => {
  if (!value) props.deleteNode()
}, {immediate: true})

function onValueUpdate(value: unknown) {
  options.value.onUpdateField(fieldId.value, {value} as Partial<BlockField>)
}

// Реальный контрол поля (Input/Select/...) в документе — превью, а не форма:
// значение по умолчанию правится в настройках, поэтому клик по нему открывает
// диалог настроек (как клик по шестерёнке), а не фокусирует ввод.
function openFieldSettings() {
  if (!canEdit.value) return
  options.value.onOpenSettings(fieldId.value)
}
</script>

<template>
  <NodeViewWrapper
      v-if="field"
      as="div"
      class="tiptap-scenario-field-node group relative my-3 rounded-lg"
      :data-field-id="fieldId"
  >
    <span
        data-drag-handle
        class="absolute top-0.5 -left-5 hidden size-4 cursor-grab items-center justify-center text-slate-300 transition hover:text-slate-500 group-hover:flex active:cursor-grabbing"
        title="Перетащить"
    >
      <GripVertical class="size-3.5"/>
    </span>

    <!-- Пустой заголовок полностью схлопывается (не занимает места) и
         разворачивается только при наведении, чтобы его можно было добавить снова. -->
    <div
        class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase outline-none transition-all"
        :class="field.type === 'hidden'
            ? 'hidden'
            : hasLabel
                ? 'mb-1 min-h-[1em]'
                : 'h-0 overflow-hidden group-hover:h-[1em] group-hover:mb-1 group-hover:overflow-visible'"
    >
      <NodeViewContent as="span"/>
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

    <!-- Остальные типы: превью реального контрола, клик открывает настройки поля -->
    <div
        v-else
        class="group/field relative"
        :class="{ 'cursor-pointer': canEdit }"
        @click="openFieldSettings"
    >
      <Settings
          v-if="canEdit"
          class="pointer-events-none absolute top-2 right-2.5 z-10 size-3.5 text-slate-300 opacity-0 transition group-hover/field:opacity-100"
      />

      <Input
          v-if="field.type === 'input'"
          v-bind="inertProps"
          :model-value="field.value"
          :placeholder="field.placeholder"
      />
      <Input
          v-else-if="field.type === 'email'"
          type="email"
          v-bind="inertProps"
          :model-value="field.value"
          :placeholder="field.placeholder"
      />
      <Input
          v-else-if="field.type === 'phone'"
          type="tel"
          v-bind="inertProps"
          :model-value="field.value"
          :placeholder="field.placeholder"
      />
      <Textarea
          v-else-if="field.type === 'textarea'"
          v-bind="inertProps"
          :model-value="field.value"
          :placeholder="field.placeholder"
          :rows="field.rows"
      />
      <Input
          v-else-if="field.type === 'number'"
          type="number"
          v-bind="inertProps"
          :model-value="field.value ?? ''"
          :placeholder="field.placeholder"
      />
      <NativeSelect
          v-else-if="field.type === 'select' && !field.multiple"
          v-bind="inertProps"
          :model-value="String(field.value ?? '')"
      >
        <option value="" disabled>Выбрать...</option>
        <option v-for="option in field.options" :key="option.id" :value="option.value">{{ option.label }}</option>
      </NativeSelect>
      <div v-else-if="field.type === 'select' && field.multiple" class="flex flex-wrap gap-1.5">
        <span
            v-for="option in field.options"
            :key="option.id"
            class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[12px] text-slate-600"
        >{{ option.label }}</span>
        <span v-if="!field.options.length" class="text-[12px] text-slate-400">Список вариантов пуст</span>
      </div>
      <Input
          v-else-if="field.type === 'date'"
          type="date"
          v-bind="inertProps"
          :model-value="field.value"
      />
      <Input
          v-else-if="field.type === 'datetime'"
          type="datetime-local"
          v-bind="inertProps"
          :model-value="field.value"
      />
      <label v-else-if="field.type === 'checkbox'" class="flex items-center gap-2 text-[13px] text-slate-700">
        <input
            type="checkbox"
            class="size-3.5 rounded border-slate-300 accent-blue-600"
            v-bind="inertProps"
            :checked="field.checked"
        />
        Значение по умолчанию
      </label>
      <Input
          v-else-if="field.type === 'hidden'"
          v-bind="inertProps"
          :model-value="field.value"
          placeholder="Скрытое значение..."
      />
      <Input
          v-else
          v-bind="inertProps"
          model-value=""
          :placeholder="options.fieldTypeLabel(field.type) + ' — настраивается через ⚙'"
      />
    </div>
  </NodeViewWrapper>
</template>
