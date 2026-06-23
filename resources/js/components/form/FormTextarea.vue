<script setup lang="ts">
import { Textarea } from '@/components/ui/textarea'
import FormField from './FormField.vue'
import { useFieldId } from './useFieldId'

const props = withDefaults(defineProps<{
    modelValue: string | null | undefined
    id?: string
    name?: string
    label?: string
    hint?: string
    error?: string
    required?: boolean
    placeholder?: string
    disabled?: boolean
    rows?: number
    autocomplete?: string
}>(), {
    required: false,
    disabled: false,
    rows: 4,
    autocomplete: 'off',
})

defineEmits<{ 'update:modelValue': [value: string] }>()

const fieldId = useFieldId(() => props.id, () => props.name)
</script>

<template>
    <FormField :label="label" :hint="hint" :error="error" :required="required" :for="fieldId">
        <Textarea
            :id="fieldId"
            :name="name || fieldId"
            :model-value="modelValue ?? ''"
            :placeholder="placeholder"
            :disabled="disabled"
            :rows="rows"
            :autocomplete="autocomplete"
            :required="required || undefined"
            :aria-invalid="!!error || undefined"
            @update:model-value="$emit('update:modelValue', String($event ?? ''))"
        />
    </FormField>
</template>
