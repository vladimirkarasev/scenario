<script setup lang="ts">
import BlockNode from '@/modules/scenario/components/flow/nodes/BlockNode.vue'
import type {ScenarioBlockData} from '@/modules/scenario/lib/scenario-flow-document'

const props = withDefaults(defineProps<{
  id: string
  data: ScenarioBlockData
  selected?: boolean
}>(), {
  selected: false,
})
</script>

<template>
  <BlockNode v-bind="props" node-kind="question" />
</template>
