<script setup lang="ts">
import {computed} from 'vue'
import {Button} from '@/components/ui/button'
import {Input} from '@/components/ui/input'
import {Label} from '@/components/ui/label'
import {Textarea} from '@/components/ui/textarea'
import DatePicker from '@/components/ui/date-picker/DatePicker.vue'
import PhoneInput from '@/components/ui/phone-input/PhoneInput.vue'
import NumberInput from '@/components/ui/number-input/NumberInput.vue'
import SurveySelectField from '@/modules/scenario/components/player/SurveySelectField.vue'
import SurveyDirectoryListField from '@/modules/scenario/components/player/SurveyDirectoryListField.vue'
import type {SurveyBlock} from '@/modules/scenario/lib/scenario-player-types'

const props = withDefaults(defineProps<{
  block: SurveyBlock
  depth?: number
}>(), {
  depth: 0,
})

const resolvedProps = computed(() => props.block.props ?? {})
const children = computed(() => ((props.block.children ?? []) as SurveyBlock[]).filter(Boolean))

function plainTextFromTipTap(value: unknown): string {
  if (!value || typeof value !== 'object') {
    return ''
  }

  const node = value as Record<string, unknown>
  const text = typeof node.text === 'string' ? node.text : ''
  const childrenText = Array.isArray(node.content)
      ? node.content.map(plainTextFromTipTap).filter(Boolean).join(' ')
      : ''

  return [text, childrenText].filter(Boolean).join(' ').trim()
}

function stripHtml(value: string): string {
  return value.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim()
}

function previewText(value: unknown): string {
  if (!value) {
    return ''
  }

  if (typeof value === 'string') {
    try {
      const parsed = JSON.parse(value)
      const parsedText = plainTextFromTipTap(parsed)

      if (parsedText) {
        return parsedText
      }
    } catch {
    }

    return stripHtml(value)
  }

  return plainTextFromTipTap(value)
}

const text = computed(() => {
  if (props.block.type === 'paragraph' || props.block.type === 'heading') {
    return String(resolvedProps.value.text ?? resolvedProps.value.content ?? '')
  }

  if (props.block.type === 'rich_text') {
    return previewText(resolvedProps.value.document ?? resolvedProps.value.defaultValue ?? resolvedProps.value.value ?? resolvedProps.value.html)
  }

  if (props.block.type === 'variable') {
    return String(resolvedProps.value.value ?? resolvedProps.value.path ?? '')
  }

  if (props.block.type === 'image') {
    return String(resolvedProps.value.alt ?? resolvedProps.value.src ?? 'Image')
  }

  if (props.block.type === 'button') {
    return String(resolvedProps.value.label ?? resolvedProps.value.text ?? 'Button')
  }

  return String(resolvedProps.value.label ?? resolvedProps.value.name ?? resolvedProps.value.key ?? props.block.id)
})

const fieldName = computed(() => String(resolvedProps.value.name ?? resolvedProps.value.key ?? props.block.id))
const fieldLabel = computed(() => String(resolvedProps.value.label ?? fieldName.value))
const placeholder = computed(() => String(resolvedProps.value.placeholder ?? ''))
const defaultValue = computed(() => String(resolvedProps.value.defaultValue ?? resolvedProps.value.value ?? ''))
const rows = computed(() => Number(resolvedProps.value.rows ?? 4))
const selectOptions = computed(() => Array.isArray(resolvedProps.value.options) ? resolvedProps.value.options : [])
const isRequired = computed(() => Boolean(resolvedProps.value.required))
const isChecked = computed(() => Boolean(resolvedProps.value.defaultValue ?? resolvedProps.value.checked))
</script>

<template>
  <p v-if="block.type === 'paragraph'" class="text-sm leading-7 text-foreground/90">
    {{ text }}
  </p>

  <div v-else-if="block.type === 'rich_text'" class="prose prose-sm max-w-none text-foreground/90">
    <p class="m-0 whitespace-pre-line text-sm leading-7">{{ text }}</p>
  </div>

  <h2 v-else-if="block.type === 'heading'" class="text-2xl font-semibold tracking-tight text-foreground">
    {{ text }}
  </h2>

  <img
      v-else-if="block.type === 'image'"
      :src="String(resolvedProps.src ?? '')"
      :alt="String(resolvedProps.alt ?? '')"
      class="max-h-72 rounded-2xl border border-border/60 object-cover"
  >

  <Button v-else-if="block.type === 'button'" type="button" variant="outline" disabled>
    {{ text || 'Button' }}
  </Button>

  <div v-else-if="block.type === 'input' || block.type === 'email'" class="grid gap-2">
    <Label :for="`preview-field-${fieldName}`">{{ fieldLabel }}</Label>
    <Input
        :id="`preview-field-${fieldName}`"
        :model-value="defaultValue"
        :required="isRequired"
        :placeholder="placeholder"
        disabled
    />
  </div>

  <div v-else-if="block.type === 'phone'" class="grid gap-2">
    <Label>{{ fieldLabel }}</Label>
    <PhoneInput :model-value="defaultValue" disabled/>
  </div>

  <div v-else-if="block.type === 'textarea'" class="grid gap-2">
    <div class="flex items-baseline justify-between">
      <Label :for="`preview-field-${fieldName}`">{{ fieldLabel }}</Label>
      <span class="text-[11px] tabular-nums text-muted-foreground">макс. {{
          Number(resolvedProps.maxLength ?? 3000)
        }}</span>
    </div>
    <Textarea
        :id="`preview-field-${fieldName}`"
        :model-value="defaultValue"
        :rows="rows"
        :maxlength="Number(resolvedProps.maxLength ?? 3000)"
        :required="isRequired"
        :placeholder="placeholder"
        disabled
    />
  </div>

  <div v-else-if="block.type === 'number'" class="grid gap-2">
    <Label>{{ fieldLabel }}</Label>
    <NumberInput
        :model-value="defaultValue !== '' ? Number(defaultValue) : null"
        :min="resolvedProps.min !== null && resolvedProps.min !== undefined ? Number(resolvedProps.min) : null"
        :max="resolvedProps.max !== null && resolvedProps.max !== undefined ? Number(resolvedProps.max) : null"
        :step="resolvedProps.step !== null && resolvedProps.step !== undefined ? Number(resolvedProps.step) : null"
        :decimal-places="Number(resolvedProps.decimalPlaces ?? 0)"
        :placeholder="placeholder"
        disabled
    />
  </div>

  <div v-else-if="block.type === 'select'" class="grid gap-2">
    <Label>{{ fieldLabel }}</Label>
    <SurveySelectField
        :model-value="Boolean(resolvedProps.multiple) ? [] : defaultValue"
        :options="selectOptions"
        :multiple="Boolean(resolvedProps.multiple)"
        :allow-root-selection="Boolean(resolvedProps.allowRootSelection ?? true)"
        disabled
    />
  </div>

  <div v-else-if="block.type === 'date' || block.type === 'datetime'" class="grid gap-2">
    <Label :for="`preview-field-${fieldName}`">{{ fieldLabel }}</Label>
    <DatePicker
        :model-value="defaultValue"
        :show-time="block.type === 'datetime'"
        disabled
    />
  </div>

  <label
      v-else-if="block.type === 'checkbox'"
      class="flex cursor-not-allowed items-start gap-3 rounded-xl border border-border/60 px-4 py-3 opacity-60"
  >
    <input
        type="checkbox"
        :checked="isChecked"
        disabled
        class="mt-0.5 size-4 shrink-0 rounded border-border accent-primary"
    />
    <span class="text-sm text-foreground">
            {{ fieldLabel }}
            <span v-if="isRequired" class="ml-0.5 text-destructive">*</span>
        </span>
  </label>

  <div v-else-if="block.type === 'directory_list'" class="grid gap-2">
    <Label>{{ fieldLabel }}</Label>
    <SurveyDirectoryListField
        :model-value="Boolean(resolvedProps.multiple) ? [] : ''"
        :directory-id="String(resolvedProps.directoryId ?? '')"
        :label-template="String(resolvedProps.labelTemplate ?? '')"
        :multiple="Boolean(resolvedProps.multiple)"
        disabled
    />
  </div>

  <div
      v-else-if="block.type === 'directory_table'"
      class="grid gap-2"
  >
    <Label>{{ fieldLabel }}</Label>
    <div
        class="rounded-xl border border-dashed border-border/60 bg-muted/30 py-4 text-center text-sm text-muted-foreground">
      Таблица справочника
    </div>
  </div>

  <input
      v-else-if="block.type === 'hidden'"
      :value="defaultValue"
      type="hidden"
  >

  <div v-else-if="block.type === 'variable'"
       class="rounded-xl border border-dashed border-border/60 bg-muted/30 px-3 py-2 text-sm">
    {{ text }}
  </div>

  <div v-else-if="block.type === 'collapse'" class="overflow-hidden rounded-xl border border-border/60">
    <button
        type="button"
        class="flex w-full items-center justify-between px-4 py-3 text-left text-sm font-medium text-foreground"
        disabled
    >
      <span>{{ fieldLabel || 'Подробнее' }}</span>
      <span class="size-4 shrink-0 text-muted-foreground">⌄</span>
    </button>
    <div class="border-t border-border/60 px-4 py-3">
      <p class="m-0 line-clamp-3 text-sm leading-7 text-foreground/90">
        {{ previewText(resolvedProps.value ?? '') }}
      </p>
    </div>
  </div>

  <div
      v-else-if="block.type === 'container' || block.type === 'group'"
      class="grid gap-4 rounded-2xl border border-border/60 bg-muted/20 p-4"
  >
    <SurveyBlockPreview
        v-for="child in children"
        :key="child.id"
        :block="child"
        :depth="depth + 1"
    />
  </div>

  <div v-else class="rounded-xl border border-dashed border-border/60 px-3 py-2 text-sm text-muted-foreground">
    Unsupported block type: {{ block.type }}
  </div>
</template>
