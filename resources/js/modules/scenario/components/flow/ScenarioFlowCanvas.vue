<script setup lang="ts">
import {computed, markRaw, ref} from 'vue'
import {
  ConnectionMode,
  MarkerType,
  VueFlow,
  type Connection,
  type DefaultEdgeOptions,
  type EdgeMouseEvent,
  type EdgeUpdateEvent,
  type NodeDragEvent,
  type NodeMouseEvent,
  type ViewportTransform,
  type XYPosition,
} from '@vue-flow/core'
import {Background} from '@vue-flow/background'
import {Controls} from '@vue-flow/controls'
import {MiniMap} from '@vue-flow/minimap'
import FlowSelectionToolbar from '@/modules/scenario/components/flow/FlowSelectionToolbar.vue'
import ParallelSmoothStepEdge from '@/modules/scenario/components/flow/edges/ParallelSmoothStepEdge.vue'
import StartNode from '@/modules/scenario/components/flow/nodes/StartNode.vue'
import BlockNode from '@/modules/scenario/components/flow/nodes/BlockNode.vue'
import QuestionNode from '@/modules/scenario/components/flow/nodes/QuestionNode.vue'
import ActionNode from '@/modules/scenario/components/flow/nodes/ActionNode.vue'
import ConditionNode from '@/modules/scenario/components/flow/nodes/ConditionNode.vue'
import EndNode from '@/modules/scenario/components/flow/nodes/EndNode.vue'
import ScenarioLinkNode from '@/modules/scenario/components/flow/nodes/ScenarioLinkNode.vue'
import {
  onFlowToolbarAfterTransition,
  onFlowToolbarEnter,
  onFlowToolbarLeave,
} from '@/modules/scenario/lib/flow-toolbar-transition'
import {scenarioFlowRenderPolicy} from '@/modules/scenario/lib/scenario-flow-performance'
import type {
  ScenarioFlowCanvasExpose,
  ScenarioFlowEdge,
  ScenarioFlowInstance,
  ScenarioFlowNode,
  ScenarioFlowNodeTypes,
} from '@/modules/scenario/types/scenario-flow-editor'

const props = defineProps<{
  nodes: ScenarioFlowNode[]
  edges: ScenarioFlowEdge[]
  viewport: ViewportTransform
  editable: boolean
  draggingNode: boolean
  selectedNode: ScenarioFlowNode | null
  selectedNodes: ScenarioFlowNode[]
  selectedEdge: ScenarioFlowEdge | null
  selectedEdgeFromCondition: boolean
}>()

const emit = defineEmits<{
  'update:nodes': [nodes: ScenarioFlowNode[]]
  'update:edges': [edges: ScenarioFlowEdge[]]
  connect: [connection: Connection]
  nodeClick: [event: NodeMouseEvent]
  nodeDoubleClick: [event: NodeMouseEvent]
  nodeDragStart: [event: NodeDragEvent]
  nodeDragStop: []
  edgeUpdate: [event: EdgeUpdateEvent]
  selectionEnd: []
  selectionDragStop: []
  edgeClick: [event: EdgeMouseEvent]
  paneClick: [event: MouseEvent]
  viewportChangeEnd: [viewport: ViewportTransform]
  editNode: []
  openCondition: []
  copy: []
  delete: []
}>()

const nodeTypes = {
  start: markRaw(StartNode),
  block: markRaw(BlockNode),
  question: markRaw(QuestionNode),
  action: markRaw(ActionNode),
  condition: markRaw(ConditionNode),
  end: markRaw(EndNode),
  scenario_link: markRaw(ScenarioLinkNode),
} as ScenarioFlowNodeTypes
const edgeTypes = {
  smoothstep: markRaw(ParallelSmoothStepEdge),
}
const defaultEdgeOptions: DefaultEdgeOptions = {
  type: 'smoothstep',
  markerEnd: {type: MarkerType.ArrowClosed, width: 18, height: 18, color: '#94a3b8'},
  style: {stroke: '#94a3b8', strokeWidth: 1.5},
}

const flowContainerRef = ref<HTMLElement | null>(null)
const flowInstance = ref<ScenarioFlowInstance | null>(null)
const viewportMoving = ref(false)
const selectionMoving = ref(false)
const onlyRenderVisibleElements = computed(
    () => scenarioFlowRenderPolicy(props.nodes.length).onlyRenderVisibleElements,
)
const renderMiniMap = computed(() => scenarioFlowRenderPolicy(props.nodes.length).renderMiniMap)
const interactionActive = computed(
    () => props.draggingNode || viewportMoving.value || selectionMoving.value,
)
const nodesModel = computed({
  get: () => props.nodes,
  set: (nodes: ScenarioFlowNode[]) => emit('update:nodes', nodes),
})
const edgesModel = computed({
  get: () => props.edges,
  set: (edges: ScenarioFlowEdge[]) => emit('update:edges', edges),
})

function onPaneReady(instance: ScenarioFlowInstance): void {
  flowInstance.value = instance
  void instance.setViewport(props.viewport)
}

function onViewportChangeStart(): void {
  viewportMoving.value = true
}

function onViewportChangeEnd(nextViewport: ViewportTransform): void {
  viewportMoving.value = false
  emit('viewportChangeEnd', nextViewport)
}

function onSelectionDragStart(): void {
  selectionMoving.value = true
}

function onSelectionDragStop(): void {
  selectionMoving.value = false
  emit('selectionDragStop')
}

function nodePosition(offset: number): XYPosition {
  const fallback = {
    x: 120 + (offset % 220),
    y: 120 + offset,
  }

  if (!flowInstance.value || !flowContainerRef.value) {
    return fallback
  }

  const rect = flowContainerRef.value.getBoundingClientRect()
  const center = flowInstance.value.screenToFlowCoordinate({
    x: rect.left + rect.width / 2,
    y: rect.top + rect.height / 2,
  })

  return {
    x: center.x + (offset % 220) - 110,
    y: center.y + (offset % 110) - 55,
  }
}

defineExpose<ScenarioFlowCanvasExpose>({nodePosition})
</script>

<template>
  <div
      class="relative min-h-0 flex-1 overflow-hidden bg-[#f8fbff]"
      :class="{'scenario-flow-canvas--interacting': interactionActive}"
  >
    <Transition
        @enter="onFlowToolbarEnter"
        @after-enter="onFlowToolbarAfterTransition"
        @leave="onFlowToolbarLeave"
        @after-leave="onFlowToolbarAfterTransition"
    >
      <FlowSelectionToolbar
          v-if="editable"
          :selected-node="selectedNode"
          :selected-edge="selectedEdge"
          :selected-nodes="selectedNodes"
          :selected-edge-from-condition="selectedEdgeFromCondition"
          @edit-node="emit('editNode')"
          @open-condition="emit('openCondition')"
          @copy="emit('copy')"
          @delete="emit('delete')"
      />
    </Transition>

    <div ref="flowContainerRef" class="h-full select-none">
      <VueFlow
          v-model:nodes="nodesModel"
          v-model:edges="edgesModel"
          class="size-full"
          :node-types="nodeTypes"
          :edge-types="edgeTypes"
          :nodes-draggable="editable"
          :nodes-connectable="editable"
          elements-selectable
          :edges-updatable="editable"
          :nodes-focusable="false"
          :edges-focusable="false"
          connect-on-click
          :connection-mode="ConnectionMode.Loose"
          :connection-radius="80"
          :only-render-visible-elements="onlyRenderVisibleElements"
          :default-viewport="viewport"
          :default-edge-options="defaultEdgeOptions"
          @connect="emit('connect', $event)"
          @node-click="emit('nodeClick', $event)"
          @node-double-click="emit('nodeDoubleClick', $event)"
          @node-drag-start="emit('nodeDragStart', $event)"
          @node-drag-stop="emit('nodeDragStop')"
          @edge-update="emit('edgeUpdate', $event)"
          @selection-end="emit('selectionEnd')"
          @selection-drag-start="onSelectionDragStart"
          @selection-drag-stop="onSelectionDragStop"
          @edge-click="emit('edgeClick', $event)"
          @pane-click="emit('paneClick', $event)"
          @pane-ready="onPaneReady"
          @viewport-change-start="onViewportChangeStart"
          @viewport-change-end="onViewportChangeEnd"
      >
        <Background pattern-color="#d7e3f1" :gap="28" />
        <MiniMap
            v-if="renderMiniMap && !interactionActive"
            class="!bottom-5 !right-5 !left-auto !top-auto overflow-hidden !rounded-2xl !border !border-slate-200 !bg-white/95 !shadow-lg"
            :node-stroke-width="3"
            mask-color="rgb(15 23 42 / 0.08)"
            pannable
            zoomable
        />
        <Controls class="!bottom-5 !left-5 !top-auto !shadow-md" />
      </VueFlow>
    </div>
  </div>
</template>

<style scoped>
.scenario-flow-canvas--interacting :deep(.scenario-flow-node *) {
  transition: none !important;
}

.scenario-flow-canvas--interacting :deep(.scenario-flow-node > div),
.scenario-flow-canvas--interacting :deep(.scenario-flow-node [class*="-node-shape"]) {
  box-shadow: none !important;
}
</style>
