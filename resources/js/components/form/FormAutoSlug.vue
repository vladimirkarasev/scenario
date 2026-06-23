<script setup lang="ts">
import {ref, watch} from 'vue'
import {Input} from '@/components/ui/input'
import {toSlug} from '@/lib/slug'
import FormField from './FormField.vue'
import {useFieldId} from './useFieldId'

const props = withDefaults(defineProps<{
  modelValue: string
  source: string
  id?: string
  name?: string
  label?: string
  hint?: string
  error?: string
  required?: boolean
  placeholder?: string
  disabled?: boolean
  autoLockOnEdit?: boolean
  autocomplete?: string
}>(), {
  required: false,
  disabled: false,
  autoLockOnEdit: true,
  autocomplete: 'off',
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const fieldId = useFieldId(() => props.id, () => props.name)
const manual = ref(props.modelValue !== '' && props.modelValue !== toSlug(props.source))

watch(() => props.source, (next) => {
  if (!manual.value) emit('update:modelValue', toSlug(next))
})

function onUserInput(value: string): void {
  if (props.autoLockOnEdit) manual.value = true
  emit('update:modelValue', value)
}
</script>

<template>
  <FormField :label="label" :hint="hint" :error="error" :required="required" :for="fieldId">
    <Input
        :id="fieldId"
        :name="name || fieldId"
        :model-value="modelValue"
        :placeholder="placeholder"
        :disabled="disabled"
        :autocomplete="autocomplete"
        :required="required || undefined"
        :aria-invalid="!!error || undefined"
        @update:model-value="onUserInput(String($event ?? ''))"
    />
  </FormField>
</template>
