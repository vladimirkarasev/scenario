<script lang="ts">
import {markRaw} from 'vue'
import {Route} from 'lucide-vue-next'

export const fieldMeta = {type: 'route', label: 'Маршрут', icon: markRaw(Route)}
</script>

<script setup lang="ts">
import {Input} from '@/components/ui/input'
import {Label} from '@/components/ui/label'
import {Select, SelectContent, SelectItem, SelectTrigger, SelectValue} from '@/components/ui/select'
import type {RouteBlockField, RoutingMode} from '../../../lib/scenario-block-fields'

defineProps<{ field: RouteBlockField; disabled?: boolean }>()
const emit = defineEmits<{ update: [patch: Partial<RouteBlockField>] }>()
defineOptions({inheritAttrs: false})
</script>

<template>
  <div class="space-y-4">
    <div class="space-y-1.5">
      <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Режим маршрута</Label>
      <Select :model-value="field.routingMode" :disabled="disabled" @update:model-value="emit('update', { routingMode: $event as RoutingMode })">
        <SelectTrigger class="w-full rounded-xl border-slate-200 text-xs" size="sm">
          <SelectValue/>
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="auto">На автомобиле</SelectItem>
          <SelectItem value="masstransit">Общественный транспорт</SelectItem>
          <SelectItem value="pedestrian">Пешком</SelectItem>
        </SelectContent>
      </Select>
    </div>

    <label class="flex h-9 items-center gap-2.5 rounded-xl border border-slate-200 bg-slate-50 px-3">
      <input
          :checked="field.showAlternatives"
          type="checkbox"
          class="size-3.5 rounded border-slate-300"
          :disabled="disabled"
          @change="emit('update', { showAlternatives: ($event.target as HTMLInputElement).checked })"
      />
      <span class="text-xs text-slate-700">Показывать альтернативные маршруты</span>
    </label>

    <div class="space-y-1.5">
      <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Максимум точек</Label>
      <Input
          :model-value="field.maxWaypoints"
          type="number"
          min="2"
          max="50"
          class="h-9 text-sm"
          :disabled="disabled"
          @update:model-value="emit('update', { maxWaypoints: Math.max(2, Number($event) || 10) })"
      />
    </div>
  </div>
</template>
