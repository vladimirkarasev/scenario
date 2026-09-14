<script setup lang="ts">
import {ref, shallowRef} from 'vue'
import {
  addEdge,
  type Connection,
  type EdgeUpdateEvent,
  type NodeDragEvent,
  type ViewportTransform,
} from '@vue-flow/core'
import FlowPalette from './FlowPalette.vue'
import FlowJsonTab from './FlowJsonTab.vue'
import ScenarioFlowCanvas from './ScenarioFlowCanvas.vue'
import ScenarioFlowOverlays from './ScenarioFlowOverlays.vue'
import {
  blockVariableFromId,
  createEmptyScenarioFlowDocument,
  createScenarioFlowNode,
  isContentNodeType,
  normalizeScenarioFlowDocument,
} from '@/modules/scenario/lib/scenario-flow-document'
import {saveScenarioVersionDraft} from '@/modules/scenario/lib/scenario-version-draft'
import {useScenarioVariables} from '@/modules/scenario/composables/useScenarioVariables'
import {useScenarioFlowClipboard} from '@/modules/scenario/composables/useScenarioFlowClipboard'
import {useScenarioFlowDocument} from '@/modules/scenario/composables/useScenarioFlowDocument'
import type {EditorTab} from '@/modules/scenario/composables/useScenarioFlowDocument'
import {useScenarioFlowInspector} from '@/modules/scenario/composables/useScenarioFlowInspector'
import {useScenarioFlowSelection} from '@/modules/scenario/composables/useScenarioFlowSelection'
import type {ScenarioBlock, ScenarioBlockData, NodeType} from '@/modules/scenario/lib/scenario-flow-document'
import type {
  ScenarioFlowEdge,
  ScenarioFlowCanvasExpose,
  ScenarioFlowEditorExpose,
  ScenarioFlowEditorProps,
  ScenarioFlowNode,
} from '@/modules/scenario/types/scenario-flow-editor'
import {provideScenarioSystemVariables} from '@/modules/scenario/composables/useScenarioSystemVariables'
import {provideScenarioNodeCatalog} from '@/modules/scenario/composables/useScenarioNodeCatalog'

const props = withDefaults(defineProps<ScenarioFlowEditorProps>(), {
  modelValue: () => createEmptyScenarioFlowDocument(),
  scenarios: () => [],
  editable: false,
  scenarioType: 'colls',
  scenarioId: null,
  versionId: null,
})

const emit = defineEmits<{
  dirtyChange: [dirty: boolean]
}>()

provideScenarioSystemVariables(() => props.scenarioType)
const {nodes: availableNodeTypes} = provideScenarioNodeCatalog(() => props.scenarioType)

const nodes = ref<ScenarioFlowNode[]>([])
const edges = ref<ScenarioFlowEdge[]>([])
const variableNodes = shallowRef<ScenarioFlowNode[]>([])
const viewport = ref<ViewportTransform>({x: 0, y: 0, zoom: 1})
const canvasRef = ref<ScenarioFlowCanvasExpose | null>(null)
const drawerOpen = ref(false)
const blockEditorDrawerOpen = ref(false)
const conditionSettingsOpen = ref(false)
const actionEditorOpen = ref(false)
const editingBlockId = ref<string | null>(null)
const editorTabs: Array<{id: EditorTab; label: string}> = [
  {id: 'editor', label: 'Редактор'},
  {id: 'json', label: 'JSON схема'},
]
const {variables: variableEntries, blocks: variableListBlocks} = useScenarioVariables(() => variableNodes.value)
let notifyChanged: () => void = () => undefined

function refreshVariableNodes(): void {
  variableNodes.value = [...nodes.value]
}

const {
  selectedNodeId,
  selectedEdgeId,
  copiedConditionVariableId,
  selectedNode,
  selectedNodes,
  selectedEdge,
  selectedEdgeFromCondition,
  conditionPreviewQuestion,
  conditionPreviewHideTitle,
  conditionPreviewContent,
  conditionPreviewOptions,
  resetFlowSelection,
  syncSelectionFromFlow,
  deleteSelected,
  onNodeClick,
  onNodeDoubleClick,
  onNodeDragStart: selectDraggedNode,
  onEdgeClick,
  onPaneClick,
  updateConditionEdgeSetting,
  openSelectedConditionSettings,
  applyConditionLogicalVariable,
} = useScenarioFlowSelection({
  nodes,
  edges,
  editable: () => props.editable,
  drawerOpen,
  conditionSettingsOpen,
  actionEditorOpen,
  resetInspectorDraft: () => resetInspectorDraft(),
  openBlockEditor,
  onChanged: () => notifyChanged(),
})

const {
  inspectorDraft,
  resetInspectorDraft,
  commitInspector,
  cancelInspector,
  syncSelectedNode,
  updateBlockVariable,
} = useScenarioFlowInspector({
  nodes,
  selectedNode,
  drawerOpen,
  scenarios: () => props.scenarios,
  onChanged: () => notifyChanged(),
})

function syncSelectedNodeAndPruneConditionEdges(): void {
  syncSelectedNode()

  const node = selectedNode.value
  if (node?.type !== 'condition') {
    return
  }

  const answerIds = new Set(node.data.conditionBranches
    .filter((answer) => answer.action === 'transition')
    .map((answer) => answer.id))
  edges.value = edges.value.filter((edge) => edge.source !== node.id
    || !edge.sourceHandle
    || answerIds.has(edge.sourceHandle))
}

function commitInspectorAndSyncGraph(): void {
  syncSelectedNodeAndPruneConditionEdges()
  commitInspector()
}

const {
  activeRightTab,
  draggingNode,
  schemaPreview,
  currentFlowDocument,
  markChanged,
  markSaved,
  onNodeDragStart: startNodeDragTracking,
  onNodeDragStop,
  switchToJsonTab,
  applyJsonEdit,
} = useScenarioFlowDocument({
  modelValue: () => props.modelValue,
  scenarios: () => props.scenarios,
  nodes,
  edges,
  viewport,
  resetSelection: resetFlowSelection,
  onDirtyChange: (dirty) => emit('dirtyChange', dirty),
  onFlowStateApplied: refreshVariableNodes,
})
notifyChanged = () => {
  refreshVariableNodes()
  markChanged()
}

function selectRightTab(tab: EditorTab): void {
  if (tab === 'json') {
    switchToJsonTab()
    return
  }

  activeRightTab.value = tab
}

function addNode(type: NodeType): void {
  if (!props.editable || !availableNodeTypes.value.some((item) => item.type === type)) {
    return
  }

  const offset = nodes.value.length * 36

  const position = canvasRef.value?.nodePosition(offset) ?? {
    x: 120 + (offset % 220),
    y: 120 + offset,
  }
  const block = createScenarioFlowNode(type, position)

  if (isContentNodeType(type)) {
    block.data.variable = blockVariableFromId(block.id)
  }

  nodes.value = [
    ...nodes.value,
    {
      id: block.id,
      type: block.type,
      position: block.position,
      data: block.data,
    },
  ]
  selectedNodeId.value = block.id
  selectedEdgeId.value = null
  notifyChanged()
}

function onConnect(connection: Connection): void {
  if (!props.editable) {
    return
  }

  const sourceNode = nodes.value.find((node) => node.id === connection.source)
  const remainingEdges = sourceNode?.type === 'condition' && connection.sourceHandle
    ? edges.value.filter((edge) => edge.source !== connection.source || edge.sourceHandle !== connection.sourceHandle)
    : edges.value

  edges.value = addEdge({
    ...connection,
    id: `edge_${Date.now()}`,
    data: {value: ''},
    type: 'smoothstep',
  }, remainingEdges) as ScenarioFlowEdge[]
  markChanged()
}

function onViewportChangeEnd(nextViewport: ViewportTransform): void {
  if (
    viewport.value.x === nextViewport.x
    && viewport.value.y === nextViewport.y
    && viewport.value.zoom === nextViewport.zoom
  ) {
    return
  }

  viewport.value = nextViewport
  markChanged()
}

function onEdgeUpdate({edge, connection}: EdgeUpdateEvent): void {
  edges.value = edges.value.map((item) => item.id === edge.id
    ? {...item, ...connection}
    : item)
  markChanged()
}

function onSelectionDragStop(): void {
  syncSelectionFromFlow()
  markChanged()
}

function onNodeDragStart(event: NodeDragEvent): void {
  selectDraggedNode(event)
  startNodeDragTracking()
}

function onConditionEdgeUpdate(payload: {key: string; value: unknown}): void {
  updateConditionEdgeSetting(payload.key, payload.value)
}

const {copySelectedNodes} = useScenarioFlowClipboard({
  nodes,
  edges,
  viewport,
  selectedNodes,
  selectedNode,
  selectedEdge,
  scenarios: () => props.scenarios,
  editable: () => props.editable,
  deleteSelected,
  onChanged: () => notifyChanged(),
})

function onBlockUpdate(block: ScenarioBlock): void {
  nodes.value = nodes.value.map((node) => node.id !== block.id ? node : {
    ...node,
    data: {...node.data, ...block.data},
  })
  notifyChanged()
}

function onActionNodeUpdate(data: Partial<ScenarioBlockData>): void {
  if (!selectedNode.value) return
  const nodeId = selectedNode.value.id
  nodes.value = nodes.value.map((node) => node.id !== nodeId ? node : {
    ...node,
    data: {...node.data, ...data},
  })
  notifyChanged()
}

function openSelectedNodeEditor(): void {
  if (!selectedNode.value) return

  if (isContentNodeType(selectedNode.value.type)) {
    openBlockEditor(selectedNode.value.id)
    return
  }

  if (selectedNode.value.type === 'action') {
    actionEditorOpen.value = true
    return
  }

  drawerOpen.value = true
}

function openBlockEditor(blockId?: string): void {
  const block = nodes.value.find((node) => node.id === (blockId ?? selectedNode.value?.id))

  if (!block || !isContentNodeType(block.type) || !props.scenarioId) {
    return
  }

  const currentDocument = normalizeScenarioFlowDocument(currentFlowDocument())

  saveScenarioVersionDraft(
      {
        scenarioId: props.scenarioId,
        versionId: props.versionId,
      },
      currentDocument,
  )

  editingBlockId.value = block.id
  blockEditorDrawerOpen.value = true
}

defineExpose<ScenarioFlowEditorExpose>({
  getDocument: currentFlowDocument,
  markSaved,
})

</script>

<template>
  <div class="grid h-full xl:grid-cols-[224px_minmax(0,1fr)]">
    <FlowPalette :editable="editable" @add="addNode" />

    <div class="flex h-full flex-col">
      <div class="flex h-11 shrink-0 items-center gap-0.5 border-b border-border/60 bg-white px-4">
        <button
            v-for="tab in editorTabs"
            :key="tab.id"
            type="button"
            class="relative inline-flex h-11 items-center px-3.5 text-sm font-medium transition"
            :class="activeRightTab === tab.id ? 'text-slate-900' : 'text-slate-400 hover:text-slate-700'"
            @click="selectRightTab(tab.id)"
        >
          {{ tab.label }}
          <span
              v-if="activeRightTab === tab.id"
              class="absolute bottom-0 left-2 right-2 h-0.5 rounded-full bg-blue-600"
          />
        </button>
      </div>

      <ScenarioFlowCanvas
          v-show="activeRightTab === 'editor'"
          ref="canvasRef"
          v-model:nodes="nodes"
          v-model:edges="edges"
          :viewport="viewport"
          :editable="editable"
          :dragging-node="draggingNode"
          :selected-node="selectedNode"
          :selected-nodes="selectedNodes"
          :selected-edge="selectedEdge"
          :selected-edge-from-condition="selectedEdgeFromCondition"
          @connect="onConnect"
          @node-click="onNodeClick"
          @node-double-click="onNodeDoubleClick"
          @node-drag-start="onNodeDragStart"
          @node-drag-stop="onNodeDragStop"
          @edge-update="onEdgeUpdate"
          @selection-end="syncSelectionFromFlow"
          @selection-drag-stop="onSelectionDragStop"
          @edge-click="onEdgeClick"
          @pane-click="onPaneClick"
          @viewport-change-end="onViewportChangeEnd"
          @edit-node="openSelectedNodeEditor"
          @open-condition="openSelectedConditionSettings"
          @copy="copySelectedNodes"
          @delete="deleteSelected"
      />

      <FlowJsonTab
          v-show="activeRightTab === 'json'"
          :schema-preview="schemaPreview"
          :editable="editable"
          @apply="applyJsonEdit"
      />
    </div>

    <ScenarioFlowOverlays
        v-model:drawer-open="drawerOpen"
        v-model:block-editor-drawer-open="blockEditorDrawerOpen"
        v-model:condition-settings-open="conditionSettingsOpen"
        v-model:action-editor-open="actionEditorOpen"
        :selected-node="selectedNode"
        :selected-edge="selectedEdge"
        :selected-edge-from-condition="selectedEdgeFromCondition"
        :condition-preview-question="conditionPreviewQuestion"
        :condition-preview-hide-title="conditionPreviewHideTitle"
        :condition-preview-content="conditionPreviewContent"
        :condition-preview-options="conditionPreviewOptions"
        :copied-condition-variable-id="copiedConditionVariableId"
        :inspector-draft="inspectorDraft"
        :variable-entries="variableEntries"
        :variable-list-blocks="variableListBlocks"
        :editing-block-id="editingBlockId"
        :editable="editable"
        :scenario-id="scenarioId"
        :version-id="versionId"
        @sync-selected-node="syncSelectedNodeAndPruneConditionEdges"
        @update-block-variable="updateBlockVariable"
        @open-block-editor="openBlockEditor"
        @commit-inspector="commitInspectorAndSyncGraph"
        @cancel-inspector="cancelInspector"
        @apply-condition-logical-variable="applyConditionLogicalVariable"
        @update-condition-edge="onConditionEdgeUpdate"
        @block-update="onBlockUpdate"
        @action-update="onActionNodeUpdate"
    />
</div>
</template>
