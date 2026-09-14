<script setup lang="ts">
import {Handle, Position} from '@vue-flow/core'
import {CircleStop} from 'lucide-vue-next'
import FlowNodeCard from '@/modules/scenario/components/flow/nodes/FlowNodeCard.vue'
import type {ScenarioBlockData} from '@/modules/scenario/lib/scenario-flow-document'

withDefaults(defineProps<{
  id: string
  data: ScenarioBlockData
  selected?: boolean
}>(), {
  selected: false,
})

</script>

<template>
  <div class="scenario-flow-node relative w-[280px]">
    <Handle id="in" type="source" :position="Position.Top" class="scenario-flow-handle !h-[10%] !w-full" connectable-start connectable-end />
    <Handle id="in_b" type="source" :position="Position.Bottom" class="scenario-flow-handle !h-[10%] !w-full" connectable-start connectable-end />
    <Handle id="left" type="source" :position="Position.Left" class="scenario-flow-handle !h-full !w-[10%]" connectable-start connectable-end />
    <Handle id="right_i" type="source" :position="Position.Right" class="scenario-flow-handle !h-full !w-[10%]" connectable-start connectable-end />

    <FlowNodeCard :node-id="id" label="Завершение" tone="orange" :selected="selected">
      <template #icon>
          <CircleStop class="size-3" />
      </template>
    </FlowNodeCard>
  </div>
</template>

<style>@import './connector.css';</style>
