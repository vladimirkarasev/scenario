<script setup lang="ts">
import {useFieldId} from './useFieldId'

const props = withDefaults(defineProps<{
  modelValue: boolean
  id?: string
  name?: string
  label: string
  description?: string
  disabled?: boolean
}>(), {
  disabled: false,
})

const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()

const fieldId = useFieldId(() => props.id, () => props.name)

function toggle(): void {
  if (props.disabled) return
  emit('update:modelValue', !props.modelValue)
}
</script>

<template>
  <label
      :for="fieldId"
      class="flex cursor-pointer items-start gap-2.5 rounded-lg px-1 py-1 transition hover:bg-slate-50"
      :class="{ 'cursor-not-allowed opacity-60': disabled }"
  >
    <input
        :id="fieldId"
        :name="name || fieldId"
        type="checkbox"
        class="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
        :checked="modelValue"
        :disabled="disabled"
        @change="toggle"
    />
    <div class="min-w-0 flex-1 space-y-0.5">
      <div class="text-[13px] font-medium text-slate-800">{{ label }}</div>
      <div v-if="description" class="text-[11px] text-slate-500">{{ description }}</div>
    </div>
  </label>
</template>
