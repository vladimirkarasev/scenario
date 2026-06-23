<script setup lang="ts">
import { NativeSelect } from '@/components/ui/native-select'
import FormField from './FormField.vue'
import { useFieldId } from './useFieldId'

const props = withDefaults(defineProps<{
    modelValue: string | number | null | undefined
    id?: string
    name?: string
    label?: string
    hint?: string
    error?: string
    required?: boolean
    disabled?: boolean
    autocomplete?: string
}>(), {
    required: false,
    disabled: false,
    autocomplete: 'off',
})

defineEmits<{ 'update:modelValue': [value: string] }>()

const fieldId = useFieldId(() => props.id, () => props.name)
</script>

<template>
    <FormField :label="label" :hint="hint" :error="error" :required="required" :for="fieldId">
        <NativeSelect
            :id="fieldId"
            :name="name || fieldId"
            :model-value="modelValue ?? ''"
            :disabled="disabled"
            :autocomplete="autocomplete"
            :required="required || undefined"
            :aria-invalid="!!error || undefined"
            @update:model-value="$emit('update:modelValue', String($event ?? ''))"
        >
            <slot />
        </NativeSelect>
    </FormField>
</template>
