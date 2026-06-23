<script setup lang="ts">
import {PALETTE_ITEMS, type FlowPaletteItem} from '@/modules/scenario/lib/scenario-flow-constants'

defineProps<{ editable: boolean }>()
const emit = defineEmits<{ add: [type: FlowPaletteItem['type']] }>()
</script>

<template>
  <aside class="flex flex-col gap-3 border-r border-border/60 bg-white p-4">
    <div class="px-1 text-[11px] font-bold uppercase tracking-wider text-slate-400">Элементы</div>
    <div class="space-y-1.5">
      <button
          v-for="item in PALETTE_ITEMS"
          :key="item.type"
          type="button"
          class="flex w-full items-center gap-3 rounded-xl border border-border/60 bg-slate-50/80 px-3 py-2.5 text-left transition hover:border-blue-300 hover:bg-blue-50 hover:shadow-sm disabled:pointer-events-none disabled:opacity-40"
          :disabled="!editable"
          @click="emit('add', item.type)"
      >
                <span
                    class="flex size-6 shrink-0 items-center justify-center rounded-lg bg-white ring-1 ring-border/60">
                    <span v-if="item.type === 'start'"
                          class="h-3 w-5 rounded-full border-2 border-blue-400 bg-slate-700"/>
                    <span v-else-if="item.type === 'block'" class="size-3.5 rounded border-2 border-blue-500 bg-white"/>
                    <span v-else-if="item.type === 'action'" class="size-3.5 border-2 border-violet-500 bg-slate-700"
                          style="clip-path: polygon(25% 0%, 75% 0%, 100% 50%, 75% 100%, 25% 100%, 0% 50%)"/>
                    <span v-else-if="item.type === 'condition'"
                          class="size-2.5 rotate-45 border-2 border-blue-500 bg-slate-700"/>
                    <span v-else-if="item.type === 'end'"
                          class="size-3.5 rounded-full border-2 border-orange-400 bg-slate-700"/>
                    <span v-else-if="item.type === 'scenario_link'"
                          class="size-3.5 rounded-full border-2 border-emerald-400 bg-emerald-400"/>
                </span>
        <span class="truncate text-sm font-medium text-slate-700">{{ item.label }}</span>
      </button>
    </div>
  </aside>
</template>
