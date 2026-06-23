<script setup lang="ts">
import { Input } from '@/components/ui/input'
import type { DirectorySchemaField } from '@/modules/directories/types/directory'

const props = withDefaults(defineProps<{
    modelValue: string
    label?: string
    fields?: DirectorySchemaField[]
    disabled?: boolean
}>(), {
    label: 'Шаблон отображения',
    fields: () => [],
    disabled: false,
})

const emit = defineEmits<{
    'update:modelValue': [value: string]
}>()

function insertKey(key: string): void {
    const token = `{{ ${key} }}`
    emit('update:modelValue', props.modelValue ? `${props.modelValue}:${token}` : token)
}
</script>

<template>
    <div class="space-y-1.5">
        <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ label }}</label>
        <Input
            :model-value="modelValue"
            placeholder="{{ поле }}"
            class="h-8 font-mono text-sm"
            :disabled="disabled"
            @update:model-value="emit('update:modelValue', String($event))"
        />
        <div v-if="fields.length" class="flex flex-wrap gap-1">
            <button
                v-for="f in fields"
                :key="f.key"
                type="button"
                class="inline-flex items-center rounded border border-slate-200 bg-slate-50 px-1.5 py-0.5 font-mono text-[10px] text-slate-600 transition hover:border-slate-300 hover:bg-slate-100 disabled:pointer-events-none disabled:opacity-40"
                :disabled="disabled"
                @click="insertKey(f.key)"
            >
                {{ f.key }}
            </button>
        </div>
    </div>
</template>
