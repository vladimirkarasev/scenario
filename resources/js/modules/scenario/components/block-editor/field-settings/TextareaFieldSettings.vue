<script lang="ts">
import { markRaw } from 'vue'
import { Rows3 } from 'lucide-vue-next'
export const fieldMeta = { type: 'textarea', label: 'Textarea', icon: markRaw(Rows3) }
</script>

<script setup lang="ts">
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import type { TextareaBlockField } from '../../../lib/scenario-block-fields'

defineProps<{ field: TextareaBlockField; disabled?: boolean }>()
const emit = defineEmits<{ update: [patch: Partial<TextareaBlockField>] }>()
defineOptions({ inheritAttrs: false })
</script>

<template>
    <div class="space-y-1.5">
        <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Placeholder</Label>
        <Input :model-value="field.placeholder" class="h-8 text-sm" :disabled="disabled" @update:model-value="emit('update', { placeholder: $event })" />
    </div>
    <div class="space-y-1.5">
        <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Строк</Label>
        <Input :model-value="String(field.rows ?? 4)" type="number" min="2" class="h-8 text-sm" :disabled="disabled" @update:model-value="emit('update', { rows: Number($event || 4) })" />
    </div>
    <div class="space-y-1.5">
        <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Макс. символов</Label>
        <Input :model-value="String(field.maxLength ?? 3000)" type="number" min="1" class="h-8 text-sm" :disabled="disabled" @update:model-value="emit('update', { maxLength: Number($event || 3000) })" />
    </div>
    <div class="space-y-1.5">
        <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">По умолчанию</Label>
        <Textarea :model-value="field.value" rows="3" :disabled="disabled" @update:model-value="emit('update', { value: $event })" />
    </div>
</template>
