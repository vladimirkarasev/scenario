<script setup lang="ts">
type Mode = 'literal' | 'template'

withDefaults(defineProps<{
    modelValue?: Mode
    literalLabel?: string
    templateLabel?: string
    disabled?: boolean
}>(), {
    modelValue: 'literal',
    literalLabel: 'Значение',
    templateLabel: 'Переменная',
    disabled: false,
})

const emit = defineEmits<{ 'update:modelValue': [v: Mode] }>()
</script>

<template>
    <div class="inline-flex items-center gap-0.5 rounded-md bg-slate-100 p-0.5 text-[10px]">
        <button
            type="button"
            :disabled="disabled"
            :class="modelValue === 'literal' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
            class="cursor-pointer rounded px-1.5 py-0.5 transition disabled:opacity-50"
            @click="emit('update:modelValue', 'literal')"
        >{{ literalLabel }}</button>
        <button
            type="button"
            :disabled="disabled"
            :class="modelValue === 'template' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
            class="cursor-pointer rounded px-1.5 py-0.5 transition disabled:opacity-50"
            @click="emit('update:modelValue', 'template')"
        >{{ templateLabel }}</button>
    </div>
</template>
