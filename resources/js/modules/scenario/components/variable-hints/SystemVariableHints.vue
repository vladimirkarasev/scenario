<script setup lang="ts">
import {Check, Copy} from 'lucide-vue-next'
import {systemFieldRef} from '@/modules/scenario/lib/scenario-variable-hints'
import type {SystemVariableGroup} from '@/modules/scenario/types/scenario-system-variable'

const props = defineProps<{
  group: SystemVariableGroup
  copiedId: string | null
}>()

defineEmits<{ copy: [text: string, id: string] }>()

function fieldId(suffix: string): string {
  return `sys:${props.group.name}.${suffix}`
}
</script>

<template>
  <div class="max-h-72 overflow-y-auto p-1">
    <button
        v-for="field in group.fields"
        :key="field.suffix"
        type="button"
        class="flex w-full flex-col gap-0.5 rounded-lg px-3 py-2 text-left transition hover:bg-slate-50"
        @click="$emit('copy', systemFieldRef(group, field), fieldId(field.suffix))"
    >
      <div class="flex items-center justify-between gap-2">
        <span class="truncate text-[12px] font-medium text-slate-700">{{ field.label }}</span>
        <Check v-if="copiedId === fieldId(field.suffix)" class="size-3 shrink-0 text-emerald-500"/>
        <Copy v-else class="size-3 shrink-0 text-slate-300"/>
      </div>
      <div class="truncate font-mono text-[10px] text-slate-400">{{ systemFieldRef(group, field) }}</div>
      <div class="truncate text-[10px] text-slate-400">{{ field.description }}</div>
    </button>
  </div>
</template>
