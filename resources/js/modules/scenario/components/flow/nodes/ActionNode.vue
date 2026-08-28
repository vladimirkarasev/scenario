<script setup lang="ts">
import {computed} from 'vue'
import {Handle, Position} from '@vue-flow/core'
import {Loader2, MousePointerClick, Zap} from 'lucide-vue-next'
import FlowNodeCard from '@/modules/scenario/components/flow/nodes/FlowNodeCard.vue'
import type {ScenarioBlockData} from '@/modules/scenario/lib/scenario-flow-document'

interface ActionPreviewItem {
  id: string
  name: string
}

const props = withDefaults(defineProps<{
  id: string
  data: ScenarioBlockData
  selected?: boolean
}>(), {
  selected: false,
})

function normalizeActionItems(value: unknown, prefix: string): ActionPreviewItem[] {
  if (!Array.isArray(value)) {
    return []
  }

  return value.map((value, index) => {
    const item = value && typeof value === 'object' ? value as Record<string, unknown> : {}

    return {
      id: `${prefix}_${String(item.id || index)}`,
      name: String(item.name || item.action_code || item.code || `Действие ${index + 1}`),
    }
  })
}

const actions = computed(() => [
  ...normalizeActionItems(props.data.before_items, 'before'),
  ...normalizeActionItems(props.data.action_items, 'action'),
])
</script>

<template>
  <div class="scenario-flow-node relative w-[280px]">
    <Handle id="in" type="source" :position="Position.Top" class="scenario-flow-handle !h-[20%] !w-full" connectable-start connectable-end />
    <Handle id="left" type="source" :position="Position.Left" class="scenario-flow-handle !h-full !w-[20%]" connectable-start connectable-end />

    <FlowNodeCard
        class="flex min-h-[150px] w-[280px] flex-col"
        :node-id="id"
        label="Действие"
        tone="violet"
        :selected="selected"
    >
      <template #icon>
        <Zap class="size-3" />
      </template>

      <div
          v-if="actions.length"
          class="pointer-events-none relative mt-2 grid select-none gap-1.5 rounded-[16px] border border-violet-100 bg-violet-50/30 px-2.5 py-2"
      >
        <div
            v-for="action in actions"
            :key="action.id"
            class="flex min-h-7 min-w-0 items-center gap-2 rounded-lg border border-slate-200 bg-white px-2 text-slate-700 shadow-sm"
        >
          <Loader2 class="size-3.5 shrink-0 animate-spin text-violet-500" />
          <span class="min-w-0 flex-1 truncate text-[9px] font-semibold">{{ action.name }}</span>
        </div>
      </div>

      <div
          v-else
          class="mt-2 flex min-h-20 flex-1 flex-col items-center justify-center gap-1.5 rounded-[16px] border border-dashed border-violet-200 text-center"
      >
        <MousePointerClick :size="16" class="text-violet-300" />
        <span class="text-[10px] leading-snug text-slate-300">
          Дважды кликните,<br>чтобы добавить действия
        </span>
      </div>
    </FlowNodeCard>

    <Handle id="out" type="source" :position="Position.Bottom" class="scenario-flow-handle !h-[20%] !w-full" connectable-start connectable-end />
    <Handle id="right" type="source" :position="Position.Right" class="scenario-flow-handle !h-full !w-4" connectable-start connectable-end />
  </div>
</template>

<style>@import './connector.css';</style>
