<script setup lang="ts">
import {Input} from '@/components/ui/input'
import FormField from './FormField.vue'
import {useFieldId} from './useFieldId'

interface CronPreset {
  label: string;
  value: string
}

const props = withDefaults(defineProps<{
  modelValue: string
  id?: string
  name?: string
  presets?: CronPreset[]
  label?: string
  hint?: string
  error?: string
  required?: boolean
  placeholder?: string
}>(), {
  required: false,
  presets: () => [],
  placeholder: '0 9 * * *',
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const fieldId = useFieldId(() => props.id, () => props.name)
</script>

<template>
  <FormField :label="label" :hint="hint" :error="error" :required="required" :for="fieldId">
    <div class="space-y-2">
      <Input
          :id="fieldId"
          :name="name || fieldId"
          :model-value="modelValue"
          :placeholder="placeholder"
          :aria-required="required || undefined"
          autocomplete="off"
          class="font-mono"
          :aria-invalid="!!error || undefined"
          @update:model-value="emit('update:modelValue', String($event ?? ''))"
      />
      <div v-if="presets.length" class="flex flex-wrap gap-1.5">
        <button
            v-for="p in presets"
            :key="p.value"
            type="button"
            class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px] font-medium text-slate-600 transition hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700"
            :class="{ 'border-blue-400 bg-blue-50 text-blue-700': modelValue === p.value }"
            @click="emit('update:modelValue', p.value)"
        >
          {{ p.label }}
        </button>
      </div>
    </div>
  </FormField>
</template>
