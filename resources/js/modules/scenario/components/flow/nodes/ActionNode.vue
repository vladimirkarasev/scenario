<script setup lang="ts">
import {computed} from 'vue'
import {Handle, Position} from '@vue-flow/core'
import {Zap} from 'lucide-vue-next'
import type {ScenarioBlockData} from '@/modules/scenario/lib/scenario-flow-document'

const props = withDefaults(defineProps<{
  data: ScenarioBlockData
  selected?: boolean
}>(), {
  selected: false,
})

const title = computed(() => props.data.title || 'Действие')
const itemsCount = computed(() => Array.isArray(props.data.action_items) ? props.data.action_items.length : 0)
const modeLabel = computed(() => props.data.execution_mode === 'parallel' ? 'параллельно' : 'последовательно')
</script>

<template>
  <div class="scenario-flow-node relative">
    <Handle id="in" type="source" :position="Position.Top" class="scenario-flow-handle !h-[20%] !w-full" connectable-start connectable-end />
    <Handle id="left" type="source" :position="Position.Left" class="scenario-flow-handle !h-full !w-[20%]" connectable-start connectable-end />

    <div class="relative flex items-center justify-center">
      <div class="action-node-shape flex size-[148px] items-center justify-center border-[3px] border-violet-500 bg-slate-800 text-center text-white shadow-[0_10px_30px_rgba(139,92,246,0.20)] transition">
        <div class="flex w-[56%] flex-col items-center gap-1.5">
          <Zap class="size-3.5 shrink-0 text-violet-400" />
          <div class="text-[13px] font-semibold leading-snug">{{ title }}</div>
          <div v-if="itemsCount" class="text-[10px] leading-none text-violet-200/80">
            {{ itemsCount }} · {{ modeLabel }}
          </div>
        </div>
      </div>
    </div>

    <svg
        class="scenario-flow-connector scenario-flow-connector--hexagon"
        :class="{'scenario-flow-connector--selected': selected}"
        viewBox="0 0 100 100"
        aria-hidden="true"
    >
      <polygon
          class="scenario-flow-connector-polygon"
          points="25 2, 75 2, 98 50, 75 98, 25 98, 2 50"
          vector-effect="non-scaling-stroke"
      />
    </svg>

    <Handle id="out" type="source" :position="Position.Bottom" class="scenario-flow-handle !h-[20%] !w-full" connectable-start connectable-end />
    <Handle id="right" type="source" :position="Position.Right" class="scenario-flow-handle !h-full !w-[20%]" connectable-start connectable-end />
  </div>
</template>

<style>@import './connector.css';</style>

<style scoped>
.action-node-shape {
  clip-path: polygon(25% 0%, 75% 0%, 100% 50%, 75% 100%, 25% 100%, 0% 50%);
}
</style>
