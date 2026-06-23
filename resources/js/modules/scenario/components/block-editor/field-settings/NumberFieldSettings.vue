<script lang="ts">
import { markRaw } from 'vue'
import { Hash } from 'lucide-vue-next'
export const fieldMeta = { type: 'number', label: 'Число', icon: markRaw(Hash) }
</script>

<script setup lang="ts">
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import type { NumberBlockField } from '../../../lib/scenario-block-fields'

defineProps<{ field: NumberBlockField; disabled?: boolean }>()
const emit = defineEmits<{ update: [patch: Partial<NumberBlockField>] }>()
defineOptions({ inheritAttrs: false })
function toNullableNum(v: string): number | null {
    return v === '' ? null : (Number.isFinite(Number(v)) ? Number(v) : null)
}
</script>

<template>
    <div class="space-y-1.5">
        <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Placeholder</Label>
        <Input :model-value="field.placeholder" class="h-8 text-sm" :disabled="disabled" @update:model-value="emit('update', { placeholder: $event })" />
    </div>
    <div class="space-y-1.5">
        <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">По умолчанию</Label>
        <Input
            :model-value="field.value ?? ''"
            type="number"
            class="h-8 text-sm"
            :disabled="disabled"
            @update:model-value="emit('update', { value: $event === '' ? null : Number($event) })"
        />
    </div>
    <div class="grid grid-cols-2 gap-3">
        <div class="space-y-1.5">
            <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Минимум</Label>
            <Input
                :model-value="field.min ?? ''"
                type="number"
                class="h-8 text-sm"
                :disabled="disabled"
                @update:model-value="emit('update', { min: toNullableNum($event) })"
            />
        </div>
        <div class="space-y-1.5">
            <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Максимум</Label>
            <Input
                :model-value="field.max ?? ''"
                type="number"
                class="h-8 text-sm"
                :disabled="disabled"
                @update:model-value="emit('update', { max: toNullableNum($event) })"
            />
        </div>
    </div>
    <div class="grid grid-cols-2 gap-3">
        <div class="space-y-1.5">
            <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Шаг</Label>
            <Input
                :model-value="field.step ?? ''"
                type="number"
                class="h-8 text-sm"
                :disabled="disabled"
                @update:model-value="emit('update', { step: toNullableNum($event) })"
            />
        </div>
        <div class="space-y-1.5">
            <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Знаков после запятой</Label>
            <Input
                :model-value="field.decimalPlaces"
                type="number"
                min="0"
                max="10"
                class="h-8 text-sm"
                :disabled="disabled"
                @update:model-value="emit('update', { decimalPlaces: Math.max(0, Math.round(Number($event) || 0)) })"
            />
        </div>
    </div>
</template>
