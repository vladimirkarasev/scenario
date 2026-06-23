<script setup lang="ts">
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import DatePicker from '@/components/ui/date-picker/DatePicker.vue'

defineProps<{
    modelValue: string
    mode: 'fixed' | 'expression'
    withTime?: boolean
}>()

const emit = defineEmits<{
    'update:modelValue': [value: string]
    'update:mode': [mode: 'fixed' | 'expression']
}>()

function setMode(mode: 'fixed' | 'expression') {
    emit('update:mode', mode)
}
</script>

<template>
    <div class="space-y-4 md:col-span-2">
        <div class="space-y-2">
            <Label>Режим значения по умолчанию</Label>
            <div class="inline-flex rounded-xl border border-slate-200 bg-slate-50 p-1">
                <button
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                    :class="mode !== 'fixed' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900'"
                    @click="setMode('expression')"
                >
                    Переменная
                </button>
                <button
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                    :class="mode === 'fixed' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900'"
                    @click="setMode('fixed')"
                >
                    Дата
                </button>
            </div>
        </div>

        <div v-if="mode === 'fixed'" class="space-y-2">
            <Label>Значение по умолчанию</Label>
            <DatePicker
                :model-value="modelValue"
                :show-time="withTime ?? false"
                clearable
                @update:model-value="emit('update:modelValue', $event)"
            />
        </div>

        <div v-else class="space-y-2">
            <Label>Значение по умолчанию</Label>
            <Input
                :model-value="modelValue"
                placeholder="{{ variable }} или now + 1 day"
                @update:model-value="emit('update:modelValue', $event)"
            />
        </div>
    </div>
</template>
