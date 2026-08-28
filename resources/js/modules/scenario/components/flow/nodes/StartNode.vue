<script setup lang="ts">
import {Handle, Position} from '@vue-flow/core'
import {Play} from 'lucide-vue-next'
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
    <FlowNodeCard :node-id="id" label="Начало" tone="cyan" :selected="selected">
      <template #icon>
          <Play class="size-3 fill-current" />
      </template>
    </FlowNodeCard>

    <Handle
        id="out"
        type="source"
        :position="Position.Bottom"
        class="scenario-flow-handle !h-4 !w-full"
        connectable-start
        :connectable-end="false"
    />
    <Handle
        id="right"
        type="source"
        :position="Position.Right"
        class="scenario-flow-handle !h-full !w-4"
        connectable-start
        :connectable-end="false"
    />
  </div>
</template>

<style>@import './connector.css';</style>
