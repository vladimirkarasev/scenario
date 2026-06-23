<script setup lang="ts">
import { ref } from 'vue'
import { Eye, EyeOff } from 'lucide-vue-next'
import { Input } from '@/components/ui/input'
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
    autocomplete?: string
}>(), {
    required: false,
    disabled: false,
    autocomplete: 'new-password',
})

defineEmits<{ 'update:modelValue': [value: string] }>()

const visible = ref(false)
const fieldId = useFieldId(() => props.id, () => props.name)
</script>

<template>
    <FormField :label="label" :hint="hint" :error="error" :required="required" :for="fieldId">
        <div class="relative">
            <Input
                :id="fieldId"
                :name="name || fieldId"
                :model-value="modelValue ?? ''"
                :type="visible ? 'text' : 'password'"
                :placeholder="placeholder"
                :disabled="disabled"
                :autocomplete="autocomplete"
                :required="required || undefined"
                :aria-invalid="!!error || undefined"
                class="pr-9"
                @update:model-value="$emit('update:modelValue', String($event ?? ''))"
            />
            <button
                type="button"
                class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 transition hover:text-slate-700"
                tabindex="-1"
                :aria-label="visible ? 'Скрыть пароль' : 'Показать пароль'"
                @click="visible = !visible"
            >
                <Eye v-if="!visible" :size="15" />
                <EyeOff v-else :size="15" />
            </button>
        </div>
    </FormField>
</template>
