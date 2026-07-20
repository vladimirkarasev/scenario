<script setup lang="ts">
/* eslint-disable vue/no-mutating-props -- formData is a reactive Record passed for in-place mutation by design */
import {computed, ref, watch} from 'vue'
import {Button} from '@/components/ui/button'
import {Input} from '@/components/ui/input'
import {Label} from '@/components/ui/label'
import {Textarea} from '@/components/ui/textarea'
import DatePicker from '@/components/ui/date-picker/DatePicker.vue'
import PhoneInput, {type PhoneValue} from '@/components/ui/phone-input/PhoneInput.vue'
import type {DirectoryListShape} from '@/lib/directory-list-shape'
import type {SelectShape} from '@/lib/select-shape'
import NumberInput from '@/components/ui/number-input/NumberInput.vue'
import TiptapTextRenderer from '@/modules/scenario/components/tiptap/TiptapTextRenderer.vue'
import SurveyDirectoryListField from '@/modules/scenario/components/player/SurveyDirectoryListField.vue'
import SurveyDirectoryTableField from '@/modules/scenario/components/player/SurveyDirectoryTableField.vue'
import SurveySelectField from '@/modules/scenario/components/player/SurveySelectField.vue'
import SurveySuggestField from '@/modules/scenario/components/player/SurveySuggestField.vue'
import type {SurveyBlock} from '@/modules/scenario/lib/scenario-player-types'
import {ChevronDown} from 'lucide-vue-next'

const props = defineProps<{
  block: SurveyBlock
  context: Record<string, unknown>
  formData: Record<string, unknown>
  errors?: Record<string, string>
  disabled?: boolean
}>()

interface SelectOption {
  id: string | number
  value: string
  label: string
  parentId?: string | number | null
}

interface DirectoryItemValue {
  id: string
  label: string
  data: Record<string, string | null>
  parent_id: string | null
  external_key: string
}

type DirectoryTableValue = DirectoryItemValue | DirectoryItemValue[] | string | string[] | null

interface FieldConfig {
  key: string
  visible: boolean
  defaultValue: string
  filterable: boolean
}

// Backend (VariableResolver) уже резолвит все шаблоны переменных сценария в props
// при формировании payload. Единственный шаблон, который остаётся сырым и подставляется
// на фронте — labelTemplate у directory_list/directory_table (он рендерится локально
// по item.data справочника через renderLabelTemplate в SurveyDirectoryListField/TableField).
const resolvedProps = computed(() => (props.block.props ?? {}) as Record<string, unknown>)
// Размер/цвет/заливка заголовка поля, заданные через bubble-menu в редакторе
// блока (BlockEditorGutenbergEditor) — применяются и здесь, и в реальном
// опросе, т.к. этот компонент общий для предпросмотра и живого плеера.
const labelStyle = computed(() => {
  const {labelFontSize, labelColor, labelHighlight} = resolvedProps.value
  const style: Record<string, string> = {}
  if (typeof labelFontSize === 'string' && labelFontSize) style.fontSize = labelFontSize
  if (typeof labelColor === 'string' && labelColor) style.color = labelColor
  if (typeof labelHighlight === 'string' && labelHighlight) {
    style.backgroundColor = labelHighlight
    style.padding = '0 4px'
  }
  return Object.keys(style).length ? style : undefined
})

interface DepDropConfig {
  fieldVarName: string
  filterKey: string
  valueKey: string
}

function resolveDepDropFilterKey(blockProps: Record<string, unknown>): string {
  const dd = blockProps.depDrop
  if (!dd || typeof dd !== 'object' || Array.isArray(dd)) return ''
  return String((dd as DepDropConfig).filterKey ?? '')
}

function resolveDepDropValue(blockProps: Record<string, unknown>, data: Record<string, unknown>): string | null {
  const dd = blockProps.depDrop
  if (!dd || typeof dd !== 'object' || Array.isArray(dd)) return null
  const config = dd as DepDropConfig
  if (!config.fieldVarName || !config.filterKey) return null

  const parentValue = data[config.fieldVarName]
  if (!parentValue || typeof parentValue !== 'object' || Array.isArray(parentValue)) return null

  const shape = parentValue as Record<string, unknown>
  const vk = config.valueKey || 'external_key'

  if (vk === 'id') return shape.id ? String(shape.id) : null
  if (vk === 'external_key') return shape.external_key ? String(shape.external_key) : null

  const dataMap = shape.data
  if (dataMap && typeof dataMap === 'object' && !Array.isArray(dataMap)) {
    const v = (dataMap as Record<string, unknown>)[vk]
    return v !== null && v !== undefined ? String(v) : null
  }
  return null
}
const resolvedChildren = computed(() => ((props.block.children ?? []) as SurveyBlock[]).filter(Boolean))
const selectOptions = computed(() => Array.isArray(resolvedProps.value.options) ? resolvedProps.value.options as SelectOption[] : [])
const directoryFields = computed(() => Array.isArray(resolvedProps.value.fields) ? resolvedProps.value.fields as FieldConfig[] : [])

const isCollapseOpen = ref(!(props.block.props?.defaultCollapsed ?? false))

const fieldName = computed(() => String(resolvedProps.value.name ?? resolvedProps.value.key ?? props.block.id))
const fieldError = computed(() => props.errors?.[fieldName.value] ?? '')
const hasError = computed(() => Boolean(fieldError.value))

watch(
    resolvedProps,
    (value) => {
      if (props.formData[fieldName.value] === undefined && value.defaultValue !== undefined) {
        props.formData[fieldName.value] = value.defaultValue
      }
    },
    {immediate: true},
)
</script>

<template>
  <p v-if="block.type === 'paragraph'" class="text-sm leading-7 text-foreground/90">
    {{ resolvedProps.text ?? '' }}
  </p>

  <TiptapTextRenderer
      v-else-if="block.type === 'rich_text'"
      :document="resolvedProps.document ?? resolvedProps.defaultValue ?? resolvedProps.value ?? null"
      :html="String(resolvedProps.html ?? '')"
  />

  <h2 v-else-if="block.type === 'heading'" class="text-2xl font-semibold tracking-tight text-foreground">
    {{ resolvedProps.text ?? resolvedProps.content ?? '' }}
  </h2>

  <img
      v-else-if="block.type === 'image'"
      :src="String(resolvedProps.src ?? '')"
      :alt="String(resolvedProps.alt ?? '')"
      class="max-h-72 rounded-2xl border border-border/60 object-cover"
  >

  <Button v-else-if="block.type === 'button'" type="button" variant="outline">
    {{ resolvedProps.label ?? resolvedProps.text ?? 'Button' }}
  </Button>

  <div v-else-if="block.type === 'input'" class="grid gap-1.5">
    <Label :for="`field-${fieldName}`" class="text-[11px] font-semibold uppercase tracking-wide text-slate-500" :style="labelStyle">{{
        resolvedProps.label ?? fieldName
      }}<span v-if="Boolean(resolvedProps.required)" class="ml-0.5 text-destructive">*</span></Label>
    <Input
        :id="`field-${fieldName}`"
        v-model="formData[fieldName]"
        class="h-9 rounded-xl"
        :class="hasError ? 'border-destructive focus-visible:ring-destructive/30' : ''"
        :disabled="disabled"
        :required="Boolean(resolvedProps.required)"
        :placeholder="String(resolvedProps.placeholder ?? '')"
    />
    <p v-if="hasError" class="text-[12px] text-destructive">{{ fieldError }}</p>
  </div>

  <div v-else-if="block.type === 'email'" class="grid gap-1.5">
    <Label :for="`field-${fieldName}`" class="text-[11px] font-semibold uppercase tracking-wide text-slate-500" :style="labelStyle">{{
        resolvedProps.label ?? fieldName
      }}<span v-if="Boolean(resolvedProps.required)" class="ml-0.5 text-destructive">*</span></Label>
    <Input
        :id="`field-${fieldName}`"
        :model-value="String(formData[fieldName] ?? '')"
        type="email"
        class="h-9 rounded-xl"
        :class="hasError ? 'border-destructive focus-visible:ring-destructive/30' : ''"
        :disabled="disabled"
        :required="Boolean(resolvedProps.required)"
        :placeholder="String(resolvedProps.placeholder ?? 'example@mail.ru')"
        @update:model-value="(v: string) => { formData[fieldName] = v }"
    />
    <p v-if="hasError" class="text-[12px] text-destructive">{{ fieldError }}</p>
  </div>

  <div v-else-if="block.type === 'phone'" class="grid gap-1.5">
    <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500" :style="labelStyle">{{
        resolvedProps.label ?? fieldName
      }}<span v-if="Boolean(resolvedProps.required)" class="ml-0.5 text-destructive">*</span></Label>
    <PhoneInput
        :model-value="(formData[fieldName] as PhoneValue | string | null) ?? null"
        :disabled="disabled"
        :error="hasError"
        @update:model-value="formData[fieldName] = $event"
    />
    <p v-if="hasError" class="text-[12px] text-destructive">{{ fieldError }}</p>
  </div>

  <div v-else-if="block.type === 'textarea'" class="grid gap-1.5">
    <div class="flex items-baseline justify-between">
      <Label :for="`field-${fieldName}`" class="text-[11px] font-semibold uppercase tracking-wide text-slate-500" :style="labelStyle">{{
          resolvedProps.label ?? fieldName
        }}<span v-if="Boolean(resolvedProps.required)" class="ml-0.5 text-destructive">*</span></Label>
      <span class="text-[11px] tabular-nums" :class="hasError ? 'text-destructive' : 'text-muted-foreground'">
                {{ String(formData[fieldName] ?? '').length }} / {{ Number(resolvedProps.maxLength ?? 3000) }}
            </span>
    </div>
    <Textarea
        :id="`field-${fieldName}`"
        v-model="formData[fieldName]"
        class="rounded-xl"
        :class="hasError ? 'border-destructive focus-visible:ring-destructive/30' : ''"
        :disabled="disabled"
        :rows="Number(resolvedProps.rows ?? 4)"
        :maxlength="Number(resolvedProps.maxLength ?? 3000)"
        :required="Boolean(resolvedProps.required)"
        :placeholder="String(resolvedProps.placeholder ?? '')"
    />
    <p v-if="hasError" class="text-[12px] text-destructive">{{ fieldError }}</p>
  </div>

  <div v-else-if="block.type === 'number'" class="grid gap-1.5">
    <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500" :style="labelStyle">{{
        resolvedProps.label ?? fieldName
      }}<span v-if="Boolean(resolvedProps.required)" class="ml-0.5 text-destructive">*</span></Label>
    <NumberInput
        :model-value="(formData[fieldName] as (number | null)) ?? null"
        :min="resolvedProps.min !== null && resolvedProps.min !== undefined ? Number(resolvedProps.min) : null"
        :max="resolvedProps.max !== null && resolvedProps.max !== undefined ? Number(resolvedProps.max) : null"
        :step="resolvedProps.step !== null && resolvedProps.step !== undefined ? Number(resolvedProps.step) : null"
        :decimal-places="Number(resolvedProps.decimalPlaces ?? 0)"
        :placeholder="String(resolvedProps.placeholder ?? '')"
        :disabled="disabled"
        :error="hasError"
        @update:model-value="formData[fieldName] = $event"
    />
    <p v-if="hasError" class="text-[12px] text-destructive">{{ fieldError }}</p>
  </div>

  <div v-else-if="block.type === 'select'" class="grid gap-1.5">
    <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500" :style="labelStyle">{{
        resolvedProps.label ?? fieldName
      }}<span v-if="Boolean(resolvedProps.required)" class="ml-0.5 text-destructive">*</span></Label>
    <SurveySelectField
        :model-value="(formData[fieldName] as SelectShape | SelectShape[] | string | string[] | null) ?? (Boolean(resolvedProps.multiple) ? [] : null)"
        :options="selectOptions"
        :multiple="Boolean(resolvedProps.multiple)"
        :allow-root-selection="Boolean(resolvedProps.allowRootSelection ?? true)"
        :default-search="String(resolvedProps.defaultSearch ?? '')"
        :disabled="disabled"
        :error="hasError"
        @update:model-value="formData[fieldName] = $event"
    />
    <p v-if="hasError" class="text-[12px] text-destructive">{{ fieldError }}</p>
  </div>

  <div v-else-if="block.type === 'date' || block.type === 'datetime'" class="grid gap-1.5">
    <Label :for="`field-${fieldName}`" class="text-[11px] font-semibold uppercase tracking-wide text-slate-500" :style="labelStyle">{{
        resolvedProps.label ?? fieldName
      }}<span v-if="Boolean(resolvedProps.required)" class="ml-0.5 text-destructive">*</span></Label>
    <DatePicker
        :model-value="String(formData[fieldName] ?? '')"
        :show-time="block.type === 'datetime'"
        :disabled="disabled"
        clearable
        @update:model-value="formData[fieldName] = $event"
    />
    <p v-if="hasError" class="text-[12px] text-destructive">{{ fieldError }}</p>
  </div>

  <div v-else-if="block.type === 'checkbox'" class="grid gap-1">
    <label
        class="flex cursor-pointer items-start gap-3 rounded-xl border px-4 py-3 transition-colors hover:bg-muted/40"
        :class="[
                hasError ? 'border-destructive' : 'border-border/60',
                disabled ? 'cursor-not-allowed opacity-60 pointer-events-none' : '',
            ]"
    >
      <input
          v-model="formData[fieldName]"
          type="checkbox"
          :disabled="disabled"
          :required="Boolean(resolvedProps.required)"
          class="mt-0.5 size-4 shrink-0 rounded border-border accent-primary disabled:cursor-not-allowed"
      />
      <span class="text-sm text-foreground" :style="labelStyle">
                {{ resolvedProps.label ?? fieldName }}
                <span v-if="Boolean(resolvedProps.required)" class="ml-0.5 text-destructive">*</span>
            </span>
    </label>
    <p v-if="hasError" class="text-[12px] text-destructive">{{ fieldError }}</p>
  </div>

  <div v-else-if="block.type === 'directory_list'" class="grid gap-1.5">
    <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500" :style="labelStyle">{{
        resolvedProps.label ?? fieldName
      }}<span v-if="Boolean(resolvedProps.required)" class="ml-0.5 text-destructive">*</span></Label>
    <SurveyDirectoryListField
        :model-value="(formData[fieldName] as DirectoryListShape | DirectoryListShape[] | string | string[] | null) ?? (Boolean(resolvedProps.multiple) ? [] : null)"
        :directory-id="String(resolvedProps.directoryId ?? '')"
        :version-id="String(resolvedProps.versionId ?? '')"
        :label-template="String(resolvedProps.labelTemplate ?? '')"
        :multiple="Boolean(resolvedProps.multiple)"
        :allow-root-selection="Boolean(resolvedProps.allowRootSelection ?? true)"
        :default-search="String(resolvedProps.defaultSearch ?? '')"
        :disabled="disabled"
        :error="hasError"
        :context="context"
        :filter-key="resolveDepDropFilterKey(resolvedProps)"
        :filter-value="resolveDepDropValue(resolvedProps, formData)"
        @update:model-value="formData[fieldName] = $event"
    />
    <p v-if="hasError" class="text-[12px] text-destructive">{{ fieldError }}</p>
  </div>

  <div v-else-if="block.type === 'directory_table'" class="grid gap-1.5">
    <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500" :style="labelStyle">{{
        resolvedProps.label ?? fieldName
      }}<span v-if="Boolean(resolvedProps.required)" class="ml-0.5 text-destructive">*</span></Label>
    <SurveyDirectoryTableField
        :model-value="(formData[fieldName] as DirectoryTableValue) ?? (Boolean(resolvedProps.multiple) ? [] : '')"
        :directory-id="String(resolvedProps.directoryId ?? '')"
        :version-id="String(resolvedProps.versionId ?? '')"
        :label-template="String(resolvedProps.labelTemplate ?? '')"
        :multiple="Boolean(resolvedProps.multiple)"
        :allow-selection="Boolean(resolvedProps.allowSelection ?? true)"
        :fields="directoryFields"
        :default-search="String(resolvedProps.defaultSearch ?? '')"
        :disabled="disabled"
        :error="hasError"
        :context="context"
        @update:model-value="formData[fieldName] = $event"
    />
    <p v-if="hasError" class="text-[12px] text-destructive">{{ fieldError }}</p>
  </div>

  <div v-else-if="block.type === 'suggest'" class="grid gap-1.5">
    <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500" :style="labelStyle">{{
        resolvedProps.label ?? fieldName
      }}<span v-if="Boolean(resolvedProps.required)" class="ml-0.5 text-destructive">*</span></Label>
    <SurveySuggestField
        :model-value="(formData[fieldName] as Record<string, unknown> | Record<string, unknown>[] | null) ?? (Boolean(resolvedProps.multiple) ? [] : null)"
        :proxy-uuid="String(resolvedProps.proxyUuid ?? '')"
        :label-field="String(resolvedProps.labelField ?? '')"
        :placeholder="String(resolvedProps.placeholder ?? '')"
        :multiple="Boolean(resolvedProps.multiple)"
        :disabled="disabled"
        :error="hasError"
        @update:model-value="formData[fieldName] = $event"
    />
    <p v-if="hasError" class="text-[12px] text-destructive">{{ fieldError }}</p>
  </div>

  <input
      v-else-if="block.type === 'hidden'"
      v-model="formData[fieldName]"
      type="hidden"
  >

  <div v-else-if="block.type === 'variable'"
       class="rounded-xl border border-dashed border-border/60 bg-muted/30 px-3 py-2 text-sm">
    {{ resolvedProps.value ?? resolvedProps.path ?? '' }}
  </div>

  <div v-else-if="block.type === 'collapse'" class="overflow-hidden rounded-xl border border-border/60">
    <button
        type="button"
        class="flex w-full items-center justify-between px-4 py-3 text-left text-sm font-medium text-foreground transition hover:bg-muted/40"
        @click="isCollapseOpen = !isCollapseOpen"
    >
      <span :style="labelStyle">{{ resolvedProps.label ?? 'Подробнее' }}</span>
      <ChevronDown
          class="size-4 shrink-0 text-muted-foreground transition-transform duration-200"
          :class="isCollapseOpen ? 'rotate-180' : ''"
      />
    </button>
    <div v-show="isCollapseOpen" class="border-t border-border/60 px-4 py-3">
      <TiptapTextRenderer
          :document="resolvedProps.document ?? resolvedProps.value ?? null"
          :html="String(resolvedProps.html ?? '')"
      />
    </div>
  </div>

  <div
      v-else-if="block.type === 'container' || block.type === 'group'"
      class="grid gap-4 rounded-2xl border border-border/60 bg-muted/20 p-4"
  >
    <SurveyBlockRenderer
        v-for="(child, index) in resolvedChildren"
        :key="child?.id ?? `child-${index}`"
        :block="child"
        :context="context"
        :form-data="formData"
        :errors="errors"
        :disabled="disabled"
    />
  </div>

  <div v-else class="rounded-xl border border-dashed border-border/60 px-3 py-2 text-sm text-muted-foreground">
    Unsupported block type: {{ block.type }}
  </div>
</template>
