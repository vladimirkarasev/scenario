<script lang="ts">
import { markRaw } from 'vue'
import { EyeOff } from 'lucide-vue-next'
export const fieldMeta = { type: 'hidden', label: 'Скрытое', icon: markRaw(EyeOff) }
</script>

<script setup lang="ts">
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import type { HiddenBlockField } from '../../../lib/scenario-block-fields'

defineProps<{ field: HiddenBlockField; disabled?: boolean }>()
const emit = defineEmits<{ update: [patch: Partial<HiddenBlockField>] }>()
</script>

<template>
    <div class="space-y-1.5">
        <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Значение</Label>
        <Input
            :model-value="field.value"
            placeholder="Значение скрытого поля"
            class="h-8 text-sm"
            :disabled="disabled"
            @update:model-value="emit('update', { value: $event })"
        />
    </div>
</template>
