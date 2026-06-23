<script lang="ts">
import { markRaw } from 'vue'
import { CalendarDays } from 'lucide-vue-next'
export const fieldMeta = { type: 'date', label: 'Дата', icon: markRaw(CalendarDays) }
</script>

<script setup lang="ts">
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import ScenarioDateTimeDefaultInput from '@/modules/scenario/components/block-editor/block-fields/ScenarioDateTimeDefaultInput.vue'
import type { DateBlockField } from '../../../lib/scenario-block-fields'

defineProps<{ field: DateBlockField; disabled?: boolean }>()
const emit = defineEmits<{ update: [patch: Partial<DateBlockField>] }>()
defineOptions({ inheritAttrs: false })
</script>

<template>
    <ScenarioDateTimeDefaultInput
        :model-value="field.value"
        :mode="field.defaultMode"
        :with-time="false"
        @update:model-value="emit('update', { value: $event })"
        @update:mode="emit('update', { defaultMode: $event })"
    />
    <div class="space-y-1.5">
        <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Формат</Label>
        <Input :model-value="field.format" placeholder="DD.MM.YYYY" class="h-8 text-sm" :disabled="disabled" @update:model-value="emit('update', { format: $event })" />
    </div>
</template>
