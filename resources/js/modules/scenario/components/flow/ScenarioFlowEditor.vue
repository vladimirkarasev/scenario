<script setup>
import {computed, markRaw, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch} from 'vue'
import {VueFlow, addEdge, ConnectionMode, MarkerType} from '@vue-flow/core'
import {Background} from '@vue-flow/background'
import {Controls} from '@vue-flow/controls'
import {MiniMap} from '@vue-flow/minimap'
import ScenarioBlockEditorDrawer from '@/modules/scenario/components/block-editor/ScenarioBlockEditorDrawer.vue'
import ActionNodeEditorDrawer from '@/modules/scenario/components/actions/ActionNodeEditorDrawer.vue'
import BlockInspector from './inspectors/BlockInspector.vue'
import ConditionInspector from './inspectors/ConditionInspector.vue'
import DefaultInspector from './inspectors/DefaultInspector.vue'
import EndInspector from './inspectors/EndInspector.vue'
import ScenarioLinkInspector from './inspectors/ScenarioLinkInspector.vue'
import FlowPalette from './FlowPalette.vue'
import FlowJsonTab from './FlowJsonTab.vue'
import FlowSelectionToolbar from './FlowSelectionToolbar.vue'
import ConditionEdgeSettingsDialog from './ConditionEdgeSettingsDialog.vue'
import StartNode from '@/modules/scenario/components/flow/nodes/StartNode.vue'
import BlockNode from '@/modules/scenario/components/flow/nodes/BlockNode.vue'
import ActionNode from '@/modules/scenario/components/flow/nodes/ActionNode.vue'
import ConditionNode from '@/modules/scenario/components/flow/nodes/ConditionNode.vue'
import EndNode from '@/modules/scenario/components/flow/nodes/EndNode.vue'
import ScenarioLinkNode from '@/modules/scenario/components/flow/nodes/ScenarioLinkNode.vue'
import {Button} from '@/components/ui/button'
import {
  Drawer,
  DrawerContent,
  DrawerDescription,
  DrawerHeader,
  DrawerTitle,
} from '@/components/ui/drawer'
import {
  cloneScenarioFlowDocument,
  createEmptyScenarioFlowDocument,
  createScenarioFlowNode,
  fromVueFlowState,
  normalizeScenarioFlowDocument,
  stringifyScenarioFlowDocument,
  toVueFlowState,
} from '@/modules/scenario/lib/scenario-flow-document'
import {saveScenarioVersionDraft} from '@/modules/scenario/lib/scenario-version-draft'
import {fieldsToVariableEntries} from '@/modules/scenario/lib/scenario-variables'
import {USER_VARIABLES} from '@/modules/scenario/lib/scenario-flow-constants'
import {X} from 'lucide-vue-next'

const props = defineProps({
  modelValue: {
    type: Object,
    default: () => createEmptyScenarioFlowDocument(),
  },
  scenarios: {
    type: Array,
    default: () => [],
  },
  editable: {
    type: Boolean,
    default: false,
  },
  scenarioId: {
    type: String,
    default: null,
  },
  versionId: {
    type: String,
    default: null,
  },
})

const emit = defineEmits(['update:modelValue'])

const nodeTypes = {
  start: markRaw(StartNode),
  block: markRaw(BlockNode),
  action: markRaw(ActionNode),
  condition: markRaw(ConditionNode),
  end: markRaw(EndNode),
  scenario_link: markRaw(ScenarioLinkNode),
}

const nodes = ref([])
const edges = ref([])
const viewport = ref({x: 0, y: 0, zoom: 1})
const flowContainerRef = ref(null)
const flowInstance = ref(null)
const selectedNodeId = ref(null)
const selectedEdgeId = ref(null)
const syncingFromModel = ref(false)
const draggingNode = ref(false)
const pendingModelSync = ref(false)
const drawerOpen = ref(false)
const blockEditorDrawerOpen = ref(false)
const conditionSettingsOpen = ref(false)
const actionEditorOpen = ref(false)
const editingBlockId = ref(null)
const inspectorDraft = reactive({})

const schemaPreview = ref('')
const activeRightTab = ref('editor')
const copiedConditionVariableId = ref(null)
// Handle IDs that belong to the "input plane" — connections started from them
// get direction-reversed so the node always ends up as the target, not the source.
// Must stay in sync with the handle ids declared in EndNode and ScenarioLinkNode.

const selectedNode = computed(() => nodes.value.find((node) => node.id === selectedNodeId.value) ?? null)
const selectedEdge = computed(() => edges.value.find((edge) => edge.id === selectedEdgeId.value) ?? null)
const selectedEdgeSourceNode = computed(() => selectedEdge.value
    ? nodes.value.find((node) => node.id === selectedEdge.value.source) ?? null
    : null)
const selectedEdgeFromCondition = computed(() => selectedEdgeSourceNode.value?.type === 'condition')

const conditionPreviewQuestion = computed(() =>
    String(selectedEdgeSourceNode.value?.data?.title || 'Условие'),
)
const conditionPreviewOptions = computed(() => {
  if (!selectedEdgeSourceNode.value) return []
  return edges.value
      .filter((e) => e.source === selectedEdgeSourceNode.value?.id)
      .map((e) => ({
        label: String(e.data?.value || e.label || '—'),
        targetNodeId: String(e.target),
      }))
})

// Полный список переменных (VariableEntry[]) для единого ScenarioVariableList
// в инспекторах action / condition / end.
const variableEntries = computed(() =>
    nodes.value
        .filter((node) => node.type === 'block')
        .flatMap((block) => fieldsToVariableEntries(
            block.data?.fields ?? [],
            block.id,
            block.data?.title || block.id,
            false,
        )),
)
const currentFlowDocument = () => fromVueFlowState({
  nodes: nodes.value,
  edges: edges.value,
  viewport: viewport.value,
})

function buildSchemaPreview() {
  const doc = currentFlowDocument()
  return JSON.stringify({
    format: doc.format,
    version: doc.version,
    viewport: doc.viewport,
    blocks: doc.blocks,
    edges: edges.value.map((e) => ({
      id: e.id,
      source: e.source,
      sourceHandle: e.sourceHandle ?? null,
      target: e.target,
      targetHandle: e.targetHandle ?? null,
      label: e.label ?? null,
      data: e.data ?? {},
    })),
  }, null, 2)
}

function updateSchemaPreview() {
  if (activeRightTab.value !== 'json') {
    return
  }

  schemaPreview.value = buildSchemaPreview()
}

function switchToJsonTab() {
  activeRightTab.value = 'json'
  schemaPreview.value = buildSchemaPreview()
}

function emitDocumentUpdate() {
  emit('update:modelValue', currentFlowDocument())
  updateSchemaPreview()
}

function flushPendingModelSync() {
  if (!pendingModelSync.value || syncingFromModel.value) {
    return
  }

  pendingModelSync.value = false
  emitDocumentUpdate()
}

function onNodeDragStart() {
  draggingNode.value = true
}

function onNodeDragStop() {
  draggingNode.value = false
  flushPendingModelSync()
}

const currentSerializedDocument = () => stringifyScenarioFlowDocument(
    fromVueFlowState({
      nodes: nodes.value,
      edges: edges.value,
      viewport: viewport.value,
    }),
)

function syncSelectionFromFlow() {
  const activeNode = nodes.value.find((node) => node.selected) ?? null
  const activeEdge = edges.value.find((edge) => edge.selected) ?? null

  if (activeNode) {
    if (selectedNodeId.value !== activeNode.id) {
      selectedNodeId.value = activeNode.id
    }

    if (selectedEdgeId.value !== null) {
      selectedEdgeId.value = null
    }

    return
  }

  if (activeEdge) {
    if (selectedEdgeId.value !== activeEdge.id) {
      selectedEdgeId.value = activeEdge.id
    }

    if (selectedNodeId.value !== null) {
      selectedNodeId.value = null
    }

    return
  }

  if (selectedNodeId.value !== null || selectedEdgeId.value !== null) {
    selectedNodeId.value = null
    selectedEdgeId.value = null
    drawerOpen.value = false
    resetInspectorDraft()
  }
}

watch(
    () => props.modelValue,
    async (value) => {
      const nextSerializedDocument = stringifyScenarioFlowDocument(value)
      const serializedCurrentDocument = currentSerializedDocument()

      if (nextSerializedDocument === serializedCurrentDocument) {
        return
      }

      syncingFromModel.value = true

      const flowState = toVueFlowState(value, props.scenarios)
      nodes.value = flowState.nodes
      edges.value = flowState.edges
      viewport.value = flowState.viewport
      selectedNodeId.value = null
      selectedEdgeId.value = null
      resetInspectorDraft()

      await nextTick()
      syncingFromModel.value = false
      pendingModelSync.value = false
      updateSchemaPreview()
    },
    {immediate: true, deep: true},
)

watch(
    () => props.scenarios,
    (scenarios) => {
      const scenarioById = new Map(scenarios.map((scenario) => [scenario.id, scenario.name]))

      nodes.value = nodes.value.map((node) => node.type === 'scenario_link'
          ? {
            ...node,
            data: {
              ...node.data,
              targetScenarioName: node.data.targetScenarioId
                  ? scenarioById.get(node.data.targetScenarioId) ?? null
                  : null,
            },
          }
          : node)
    },
    {deep: true},
)

watch(
    [nodes, edges, viewport],
    () => {
      syncSelectionFromFlow()

      if (syncingFromModel.value) {
        return
      }

      if (draggingNode.value) {
        pendingModelSync.value = true

        return
      }

      pendingModelSync.value = false
      emitDocumentUpdate()
    },
    {deep: true},
)

// Снимок data ноды на момент открытия drawer — для отката по «Отменить».
const inspectorSnapshot = ref(null)

watch(
    () => selectedNodeId.value,
    (nodeId) => {
      const node = nodeId
          ? nodes.value.find((item) => item.id === nodeId) ?? null
          : null

      if (!node) {
        resetInspectorDraft()
        inspectorSnapshot.value = null

        return
      }

      Object.assign(inspectorDraft, cloneScenarioFlowDocument({
        format: 'scenario-flow',
        version: 1,
        viewport: {x: 0, y: 0, zoom: 1},
        blocks: [{
          id: node.id,
          type: node.type,
          position: node.position,
          data: node.data,
        }],
        connections: [],
      }).blocks[0].data)

      inspectorSnapshot.value = JSON.parse(JSON.stringify(node.data))
    },
)

function resetInspectorDraft() {
  Object.keys(inspectorDraft).forEach((key) => {
    delete inspectorDraft[key]
  })
}

function commitInspector() {
  drawerOpen.value = false
}

function cancelInspector() {
  if (!selectedNode.value || !inspectorSnapshot.value) {
    drawerOpen.value = false
    return
  }
  const snapshot = inspectorSnapshot.value
  nodes.value = nodes.value.map((node) =>
      node.id === selectedNode.value.id
          ? {...node, data: JSON.parse(JSON.stringify(snapshot))}
          : node,
  )
  drawerOpen.value = false
}

function syncSelectedNode() {
  if (!selectedNode.value) {
    return
  }

  let nextNode = null

  nodes.value = nodes.value.map((node) => {
    if (node.id !== selectedNode.value.id) {
      return node
    }

    nextNode = {
      ...node,
      data: {
        ...node.data,
        ...normalizeScenarioFlowDocument({
          format: 'scenario-flow',
          version: 1,
          viewport: {x: 0, y: 0, zoom: 1},
          blocks: [{
            id: node.id,
            type: node.type,
            position: node.position,
            data: inspectorDraft,
          }],
          connections: [],
        }).blocks[0].data,
        targetScenarioName: inspectorDraft.targetScenarioId
            ? inspectorDraft.targetScenarioName
            ?? props.scenarios.find((scenario) => scenario.id === inspectorDraft.targetScenarioId)?.name
            ?? null
            : null,
        targetVersionName: inspectorDraft.targetVersionId
            ? inspectorDraft.targetVersionName ?? null
            : null,
      },
    }

    return nextNode
  })

}

function normalizeVariableName(value) {
  return String(value ?? '').replace(/\s+/g, '_')
}

function updateBlockVariable(value) {
  inspectorDraft.variable = normalizeVariableName(value)
  syncSelectedNode()
}

function addNode(type) {
  if (!props.editable) {
    return
  }

  const offset = nodes.value.length * 36

  let cx = 120 + (offset % 220)
  let cy = 120 + offset

  if (flowInstance.value && flowContainerRef.value) {
    const rect = flowContainerRef.value.getBoundingClientRect()
    const center = flowInstance.value.screenToFlowCoordinate({
      x: rect.left + rect.width / 2,
      y: rect.top + rect.height / 2,
    })
    cx = center.x + (offset % 220) - 110
    cy = center.y + (offset % 110) - 55
  }

  const block = createScenarioFlowNode(type, {x: cx, y: cy})

  if (type === 'block') {
    // variable должна быть уникальна (используется в шаблонах).
    const suffix = block.id.slice(block.id.indexOf('_') + 1)
    block.data.variable = `block_${suffix}`
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
}

function deleteSelected() {
  if (!props.editable) {
    return
  }

  if (selectedNode.value) {
    const nodeId = selectedNode.value.id
    nodes.value = nodes.value.filter((node) => node.id !== nodeId)
    edges.value = edges.value.filter((edge) => edge.source !== nodeId && edge.target !== nodeId)
    selectedNodeId.value = null
    conditionSettingsOpen.value = false
    actionEditorOpen.value = false
    drawerOpen.value = false
    resetInspectorDraft()

    return
  }

  if (selectedEdge.value) {
    edges.value = edges.value.filter((edge) => edge.id !== selectedEdge.value.id)
    selectedEdgeId.value = null
    drawerOpen.value = false
  }
}

function onConnect(connection) {
  if (!props.editable) {
    return
  }

  // Direction уже корректный: input handles имеют type="target", output handles —
  // type="source", поэтому Vue Flow сам выставляет edge.source / edge.target.
  edges.value = addEdge({
    ...connection,
    id: `edge_${Date.now()}`,
    label: null,
    data: {value: ''},
    type: 'smoothstep',
  }, edges.value)
}

function onNodeClick({event, node}) {
  event?.stopPropagation?.()

  if (!isPointInsideNodeShape(event, node)) {
    return
  }

  selectedNodeId.value = node.id
  selectedEdgeId.value = null
}

function onNodeDoubleClick({event, node}) {
  event?.stopPropagation?.()

  if (!isPointInsideNodeShape(event, node)) {
    return
  }

  selectedNodeId.value = node.id
  selectedEdgeId.value = null

  if (node.type === 'block') {
    openSelectedBlockEditor()
    return
  }

  if (node.type === 'action') {
    actionEditorOpen.value = true
    return
  }

  drawerOpen.value = true
}

function isPointInsideNodeShape(event, node) {
  if (event?.target?.closest?.('.vue-flow__handle')) {
    return true
  }

  if (!['condition', 'end', 'scenario_link'].includes(node.type)) {
    return true
  }

  const element = event?.target?.closest?.('.scenario-flow-node')
  if (!element) {
    return true
  }

  const rect = element.getBoundingClientRect()
  const x = event.clientX - rect.left
  const y = event.clientY - rect.top
  const halfWidth = rect.width / 2
  const halfHeight = rect.height / 2
  const dx = Math.abs(x - halfWidth)
  const dy = Math.abs(y - halfHeight)

  if (node.type === 'condition') {
    return dx / halfWidth + dy / halfHeight <= 1
  }

  return (dx * dx) / (halfWidth * halfWidth) + (dy * dy) / (halfHeight * halfHeight) <= 1
}

function onEdgeClick({event, edge}) {
  event?.stopPropagation?.()
  selectedEdgeId.value = edge.id
  selectedNodeId.value = null
  conditionSettingsOpen.value = false
  drawerOpen.value = false
  resetInspectorDraft()
}

// Перекрашиваем линии в зависимости от текущего выбора. Только цвет, без изменения
// толщины — иначе рекурсия с deep-watch на edges ломает реактивность Vue Flow.
watch(selectedEdgeId, (id) => {
  for (const edge of edges.value) {
    const isSelected = edge.id === id
    const color = isSelected ? '#2563eb' : '#94a3b8'
    edge.style = {...(edge.style ?? {}), stroke: color}
    edge.markerEnd = {...(edge.markerEnd ?? {}), type: 'arrowclosed', width: 18, height: 18, color}
  }
})

function onPaneClick(event) {
  if (event?.target?.closest?.('.vue-flow__node, .vue-flow__edge')) {
    return
  }

  selectedNodeId.value = null
  selectedEdgeId.value = null
  conditionSettingsOpen.value = false
  drawerOpen.value = false
  resetInspectorDraft()
}

function onPaneReady(instance) {
  flowInstance.value = instance
  instance.setViewport(viewport.value)
}

function onViewportChangeEnd(nextViewport) {
  viewport.value = nextViewport
}

function updateConditionEdgeSetting(key, value) {
  if (!selectedEdge.value) {
    return
  }

  edges.value = edges.value.map((edge) => edge.id === selectedEdge.value.id
      ? {
        ...edge,
        label: key === 'value' ? (value || null) : edge.label,
        data: {...(edge.data ?? {}), [key]: value},
      }
      : edge)
}

function openSelectedConditionSettings() {
  if (!selectedEdgeFromCondition.value) {
    return
  }

  conditionSettingsOpen.value = true
}

function applyConditionLogicalVariable(variable) {
  updateConditionEdgeSetting('value', variable.value)
  copiedConditionVariableId.value = variable.id
  setTimeout(() => {
    copiedConditionVariableId.value = null
  }, 1500)
}

function handleDeleteShortcut(event) {
  const target = event.target

  if (
      target instanceof HTMLElement
      && (
          ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName)
          || target.isContentEditable
      )
  ) {
    return
  }

  if (event.key !== 'Delete' && event.key !== 'Backspace') {
    return
  }

  if (!selectedNode.value && !selectedEdge.value) {
    return
  }

  event.preventDefault()
  deleteSelected()
}

onMounted(() => {
  window.addEventListener('keydown', handleDeleteShortcut)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleDeleteShortcut)
})

function onBlockUpdate(block) {
  nodes.value = nodes.value.map((node) => node.id !== block.id ? node : {
    ...node,
    data: {...node.data, ...block.data},
  })
  emitDocumentUpdate()
}

function onActionNodeUpdate(data) {
  if (!selectedNode.value) return
  nodes.value = nodes.value.map((node) => node.id !== selectedNode.value.id ? node : {
    ...node,
    data: {...node.data, ...data},
  })
  emitDocumentUpdate()
}

function openSelectedNodeEditor() {
  if (!selectedNode.value) return

  if (selectedNode.value.type === 'block') {
    openSelectedBlockEditor()
    return
  }

  if (selectedNode.value.type === 'action') {
    actionEditorOpen.value = true
    return
  }

  drawerOpen.value = true
}

function openSelectedBlockEditor() {
  if (!selectedNode.value || selectedNode.value.type !== 'block') {
    return
  }

  const currentDocument = normalizeScenarioFlowDocument(fromVueFlowState({
    nodes: nodes.value,
    edges: edges.value,
    viewport: viewport.value,
  }))

  saveScenarioVersionDraft(
      {
        scenarioId: props.scenarioId,
        versionId: props.versionId,
      },
      currentDocument,
  )

  editingBlockId.value = selectedNode.value.id
  blockEditorDrawerOpen.value = true
}

function onToolbarEnter(el) {
  el.style.overflow = 'hidden'
  el.style.height = '0'
  el.style.opacity = '0'
  el.style.transform = 'translateY(-4px)'
  requestAnimationFrame(() => {
    el.style.transition = 'height 0.25s cubic-bezier(0.4,0,0.2,1), opacity 0.2s ease, transform 0.25s cubic-bezier(0.4,0,0.2,1)'
    el.style.height = el.scrollHeight + 'px'
    el.style.opacity = '1'
    el.style.transform = 'translateY(0)'
  })
}

function onToolbarAfterEnter(el) {
  el.style.transition = ''
  el.style.height = ''
  el.style.overflow = ''
  el.style.opacity = ''
  el.style.transform = ''
}

function onToolbarLeave(el) {
  el.style.overflow = 'hidden'
  el.style.height = el.scrollHeight + 'px'
  el.style.opacity = '1'
  el.style.transform = 'translateY(0)'
  requestAnimationFrame(() => {
    el.style.transition = 'height 0.25s cubic-bezier(0.4,0,0.2,1), opacity 0.2s ease, transform 0.25s cubic-bezier(0.4,0,0.2,1)'
    el.style.height = '0'
    el.style.opacity = '0'
    el.style.transform = 'translateY(-4px)'
  })
}

function onToolbarAfterLeave(el) {
  el.style.transition = ''
  el.style.height = ''
  el.style.overflow = ''
  el.style.opacity = ''
  el.style.transform = ''
}

function applyJsonEdit(parsed) {
  const normalized = normalizeScenarioFlowDocument(parsed)
  const flowState = toVueFlowState(normalized, props.scenarios)

  syncingFromModel.value = true
  nodes.value = flowState.nodes
  edges.value = flowState.edges
  viewport.value = flowState.viewport
  selectedNodeId.value = null
  selectedEdgeId.value = null
  resetInspectorDraft()

  nextTick(() => {
    syncingFromModel.value = false
    emitDocumentUpdate()
  })
}
</script>

<template>
  <div class="grid h-full xl:grid-cols-[224px_minmax(0,1fr)]">
    <FlowPalette :editable="editable" @add="addNode"/>

    <div class="flex h-full flex-col">
      <div class="flex h-11 shrink-0 items-center gap-0.5 border-b border-border/60 bg-white px-4">
        <button
            v-for="tab in [{ id: 'editor', label: 'Редактор' }, { id: 'json', label: 'JSON схема' }]"
            :key="tab.id"
            type="button"
            class="relative inline-flex h-11 items-center px-3.5 text-sm font-medium transition"
            :class="activeRightTab === tab.id ? 'text-slate-900' : 'text-slate-400 hover:text-slate-700'"
            @click="tab.id === 'json' ? switchToJsonTab() : (activeRightTab = tab.id)"
        >
          {{ tab.label }}
          <span
              v-if="activeRightTab === tab.id"
              class="absolute bottom-0 left-2 right-2 h-0.5 rounded-full bg-blue-600"
          />
        </button>
      </div>

      <div v-show="activeRightTab === 'editor'" class="relative flex-1 min-h-0 overflow-hidden bg-[#f8fbff]">
        <Transition
            @enter="onToolbarEnter"
            @after-enter="onToolbarAfterEnter"
            @leave="onToolbarLeave"
            @after-leave="onToolbarAfterLeave"
        >
          <FlowSelectionToolbar
              v-if="editable"
              :selected-node="selectedNode"
              :selected-edge="selectedEdge"
              :selected-edge-from-condition="selectedEdgeFromCondition"
              @edit-node="openSelectedNodeEditor"
              @open-condition="openSelectedConditionSettings"
              @delete="deleteSelected"
          />
        </Transition>

        <div ref="flowContainerRef" class="h-full">
          <VueFlow
              v-model:nodes="nodes"
              v-model:edges="edges"
              class="size-full"
              :node-types="nodeTypes"
              :nodes-draggable="editable"
              :nodes-connectable="editable"
              elements-selectable
              :edges-updatable="editable"
              :nodes-focusable="false"
              :edges-focusable="false"
              connect-on-click
              :connection-mode="ConnectionMode.Loose"
              :connection-radius="80"
              :default-viewport="viewport"
              :default-edge-options="{
                        type: 'smoothstep',
                        markerEnd: { type: MarkerType.ArrowClosed, width: 18, height: 18, color: '#94a3b8' },
                        style: { stroke: '#94a3b8', strokeWidth: 1.5 },
                    }"
              @connect="onConnect"
              @node-click="onNodeClick"
              @node-double-click="onNodeDoubleClick"
              @node-drag-start="onNodeDragStart"
              @node-drag-stop="onNodeDragStop"
              @edge-click="onEdgeClick"
              @pane-click="onPaneClick"
              @pane-ready="onPaneReady"
              @viewport-change-end="onViewportChangeEnd"
          >
            <Background pattern-color="#d7e3f1" :gap="28"/>
            <MiniMap
                v-if="!draggingNode"
                class="!bottom-5 !right-5 !left-auto !top-auto overflow-hidden !rounded-2xl !border !border-slate-200 !bg-white/95 !shadow-lg"
                :node-stroke-width="3"
                :mask-color="'rgb(15 23 42 / 0.08)'"
                pannable
                zoomable
            />
            <Controls class="!bottom-5 !left-5 !top-auto !shadow-md"/>
          </VueFlow>
        </div>
      </div>

      <FlowJsonTab
          v-show="activeRightTab === 'json'"
          :schema-preview="schemaPreview"
          :editable="editable"
          @apply="applyJsonEdit"
      />
    </div>

    <Drawer v-model:open="drawerOpen" direction="right" handle-only>
      <DrawerContent
          class="h-full"
          :class="selectedNode?.type === 'condition' || selectedNode?.type === 'end'
                    ? '!w-[45vw] !max-w-[45vw]'
                    : 'sm:max-w-md'"
          @pointer-down-outside="$event.preventDefault()"
          @focus-outside="$event.preventDefault()"
          @interact-outside="$event.preventDefault()"
      >
        <template v-if="selectedNode?.type === 'condition'">
          <DrawerTitle class="sr-only">Inspector: Condition</DrawerTitle>
          <DrawerDescription class="sr-only">Condition editor</DrawerDescription>

          <div class="flex shrink-0 items-center justify-end border-b border-slate-200 px-3" style="height:40px">
            <button
                type="button"
                class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                @click="drawerOpen = false"
            >
              <X class="size-4"/>
            </button>
          </div>
        </template>

        <DrawerHeader v-else class="border-b border-slate-200 text-left">
          <DrawerTitle>Инспектор</DrawerTitle>
          <DrawerDescription class="sr-only">Inspector</DrawerDescription>
        </DrawerHeader>

        <div class="min-h-0 flex-1 overflow-hidden">
          <ConditionInspector
              v-if="selectedNode?.type === 'condition'"
              :node="selectedNode"
              :draft="inspectorDraft"
              :editable="editable"
              :variables="variableEntries"
              :blocks="nodes"
              @sync="syncSelectedNode"
          />

          <EndInspector
              v-else-if="selectedNode?.type === 'end'"
              :node="selectedNode"
              :draft="inspectorDraft"
              :editable="editable"
              :variables="variableEntries"
              :blocks="nodes"
              @sync="syncSelectedNode"
          />

          <div v-else-if="selectedNode" class="space-y-4 overflow-y-auto px-4 py-4">
            <BlockInspector
                v-if="selectedNode.type === 'block'"
                :node="selectedNode"
                :draft="inspectorDraft"
                :editable="editable"
                :scenario-id="scenarioId"
                :version-id="versionId"
                @sync="syncSelectedNode"
                @update-variable="updateBlockVariable"
                @open-editor="openSelectedBlockEditor"
            />

            <ScenarioLinkInspector
                v-else-if="selectedNode.type === 'scenario_link'"
                :node="selectedNode"
                :draft="inspectorDraft"
                :editable="editable"
                :scenario-id="scenarioId"
                @sync="syncSelectedNode"
            />

            <template v-else>
              <DefaultInspector :node="selectedNode"/>

              <div v-if="selectedNode.type === 'action'" class="rounded-2xl border border-blue-100 bg-blue-50 p-3">
                <div class="flex flex-col gap-3">
                  <div class="text-sm font-medium text-blue-900">Редактор шага</div>
                  <Button class="w-full gap-2" @click="actionEditorOpen = true">
                    Редактировать
                  </Button>
                </div>
              </div>
            </template>
          </div>
        </div>

        <div v-if="selectedNode && selectedNode.type !== 'condition'"
             class="shrink-0 flex items-center justify-end gap-2 border-t border-slate-200 px-4 py-3">
          <Button type="button" variant="outline" @click="cancelInspector">Отменить</Button>
          <Button type="button" :disabled="!editable" @click="commitInspector">Сохранить</Button>
        </div>
      </DrawerContent>
    </Drawer>

    <ConditionEdgeSettingsDialog
        v-model:open="conditionSettingsOpen"
        :selected-edge="selectedEdge"
        :selected-edge-from-condition="selectedEdgeFromCondition"
        :condition-preview-question="conditionPreviewQuestion"
        :condition-preview-options="conditionPreviewOptions"
        :editable="editable"
        :copied-condition-variable-id="copiedConditionVariableId"
        @apply-logical="applyConditionLogicalVariable"
        @update-edge="(p) => updateConditionEdgeSetting(p.key, p.value)"
    />

    <ScenarioBlockEditorDrawer
        v-if="editingBlockId"
        v-model:open="blockEditorDrawerOpen"
        :scenario-id="scenarioId"
        :version-id="versionId"
        :block-id="editingBlockId"
        @update:block="onBlockUpdate"
    />

    <ActionNodeEditorDrawer
        v-if="selectedNode?.type === 'action'"
        v-model:open="actionEditorOpen"
        :node-id="selectedNode.id"
        :node-data="selectedNode.data"
        :editable="editable"
        :variables="variableEntries"
        :blocks="nodes"
        :user-variables="USER_VARIABLES"
        @update="onActionNodeUpdate"
    />

  </div>
</template>
