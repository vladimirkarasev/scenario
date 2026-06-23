<script setup lang="ts">
import { useFieldId } from './useFieldId'

const props = withDefaults(defineProps<{
    modelValue: boolean
    id?: string
    label: string
    description?: string
    disabled?: boolean
}>(), {
    disabled: false,
})

const emit = defineEmits<{
    'update:modelValue': [value: boolean]
}>()

const fieldId = useFieldId(() => props.id)

function toggle(): void {
    if (props.disabled) return
    emit('update:modelValue', !props.modelValue)
}
</script>

<template>
    <button
        :id="fieldId"
        type="button"
        role="switch"
        :aria-checked="modelValue"
        :aria-label="label"
        class="flex w-full items-center justify-between rounded-xl border px-4 py-3 text-left transition disabled:cursor-not-allowed disabled:opacity-60"
        :class="modelValue ? 'border-blue-300 bg-blue-50' : 'border-slate-200 bg-white hover:border-slate-300'"
        :disabled="disabled"
        @click="toggle"
    >
        <div class="space-y-0.5">
            <div class="text-[13px] font-medium" :class="modelValue ? 'text-blue-800' : 'text-slate-700'">{{ label }}</div>
            <div v-if="description" class="text-[11px]" :class="modelValue ? 'text-blue-500' : 'text-slate-400'">{{ description }}</div>
        </div>
        <div class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors" :class="modelValue ? 'bg-blue-500' : 'bg-slate-200'">
            <span class="pointer-events-none inline-block h-4 w-4 rounded-full bg-white shadow-sm transition-transform" :class="modelValue ? 'translate-x-4' : 'translate-x-0.5'" />
        </div>
    </button>
</template>
