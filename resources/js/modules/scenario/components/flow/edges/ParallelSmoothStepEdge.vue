<script setup lang="ts">
import {computed} from 'vue'
import {
  BaseEdge,
  getSmoothStepPath,
  useVueFlow,
  type EdgeProps,
} from '@vue-flow/core'
import {
  distributedEdgeOffset,
  offsetEdgeTarget,
} from '@/modules/scenario/lib/flow-edge-spacing'

const props = defineProps<EdgeProps>()

const {getEdges} = useVueFlow()
const siblingEdgeIds = computed(() => getEdges.value
  .filter((edge) => edge.target === props.target
    && (edge.targetHandle ?? null) === (props.targetHandleId ?? null))
  .map((edge) => edge.id))
const targetOffset = computed(() => distributedEdgeOffset(
  siblingEdgeIds.value.indexOf(props.id),
  siblingEdgeIds.value.length,
))
const spacedTarget = computed(() => offsetEdgeTarget(
  props.targetX,
  props.targetY,
  props.targetPosition,
  targetOffset.value,
))
const edgePath = computed(() => getSmoothStepPath({
  sourceX: props.sourceX,
  sourceY: props.sourceY,
  sourcePosition: props.sourcePosition,
  targetX: spacedTarget.value.x,
  targetY: spacedTarget.value.y,
  targetPosition: props.targetPosition,
}))
</script>

<template>
  <BaseEdge
      :id="id"
      :path="edgePath[0]"
      :label-x="edgePath[1]"
      :label-y="edgePath[2]"
      :label="label"
      :marker-start="markerStart"
      :marker-end="markerEnd"
      :interaction-width="interactionWidth"
      :style="style"
  />
</template>
