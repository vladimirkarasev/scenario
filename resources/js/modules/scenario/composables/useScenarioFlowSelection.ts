import {computed, ref, watch, type Ref} from 'vue'
import {MarkerType, type EdgeMouseEvent, type NodeMouseEvent} from '@vue-flow/core'
import type {FlowLogicalVariable} from '@/modules/scenario/lib/scenario-flow-constants'
import type {
    ScenarioFlowEdge,
    ScenarioFlowNode,
} from '@/modules/scenario/types/scenario-flow-editor'

interface ScenarioFlowSelectionOptions {
    nodes: Ref<ScenarioFlowNode[]>
    edges: Ref<ScenarioFlowEdge[]>
    editable: () => boolean
    drawerOpen: Ref<boolean>
    conditionSettingsOpen: Ref<boolean>
    actionEditorOpen: Ref<boolean>
    resetInspectorDraft: () => void
    openBlockEditor: (blockId: string) => void
    onChanged: () => void
}

export function useScenarioFlowSelection(options: ScenarioFlowSelectionOptions) {
    const selectedNodeId = ref<string | null>(null)
    const selectedEdgeId = ref<string | null>(null)
    const copiedConditionVariableId = ref<string | null>(null)

    const selectedNode = computed(() => options.nodes.value.find((node) => node.id === selectedNodeId.value) ?? null)
    const selectedNodes = computed(() => options.nodes.value.filter((node) => node.selected))
    const selectedEdge = computed(() => options.edges.value.find((edge) => edge.id === selectedEdgeId.value) ?? null)
    const selectedEdgeSourceNode = computed(() => {
        const edge = selectedEdge.value

        return edge ? options.nodes.value.find((node) => node.id === edge.source) ?? null : null
    })
    const selectedEdgeFromCondition = computed(() => selectedEdgeSourceNode.value?.type === 'condition')
    const conditionPreviewQuestion = computed(() => String(selectedEdgeSourceNode.value?.data.title || 'Условие'))
    const conditionPreviewHideTitle = computed(() => Boolean(selectedEdgeSourceNode.value?.data.hideTitle ?? true))
    const conditionPreviewContent = computed(() => selectedEdgeSourceNode.value?.data.content ?? null)
    const conditionPreviewOptions = computed(() => {
        if (!selectedEdgeSourceNode.value) {
            return []
        }

        return options.edges.value
            .filter((edge) => edge.source === selectedEdgeSourceNode.value?.id)
            .map((edge) => ({
                label: String(edge.data?.value || edge.label || '—'),
                targetNodeId: edge.target,
            }))
    })

    function resetFlowSelection(): void {
        selectedNodeId.value = null
        selectedEdgeId.value = null
        options.drawerOpen.value = false
        options.resetInspectorDraft()
    }

    function syncSelectionFromFlow(): void {
        const activeNode = options.nodes.value.find((node) => node.selected) ?? null
        const activeEdge = options.edges.value.find((edge) => edge.selected) ?? null

        if (activeNode) {
            selectedNodeId.value = activeNode.id
            selectedEdgeId.value = null
            return
        }

        if (activeEdge) {
            selectedEdgeId.value = activeEdge.id
            selectedNodeId.value = null
            return
        }

        if (selectedNodeId.value !== null || selectedEdgeId.value !== null) {
            resetFlowSelection()
        }
    }

    function deleteSelected(): void {
        if (!options.editable()) {
            return
        }

        if (selectedNodes.value.length > 1) {
            const nodeIds = new Set(selectedNodes.value.map((node) => node.id))
            options.nodes.value = options.nodes.value.filter((node) => !nodeIds.has(node.id))
            options.edges.value = options.edges.value.filter((edge) => !nodeIds.has(edge.source) && !nodeIds.has(edge.target))
            closeSelectionEditors()
            options.onChanged()
            return
        }

        if (selectedNode.value) {
            const nodeId = selectedNode.value.id
            options.nodes.value = options.nodes.value.filter((node) => node.id !== nodeId)
            options.edges.value = options.edges.value.filter((edge) => edge.source !== nodeId && edge.target !== nodeId)
            closeSelectionEditors()
            options.onChanged()
            return
        }

        if (selectedEdge.value) {
            const edgeId = selectedEdge.value.id
            options.edges.value = options.edges.value.filter((edge) => edge.id !== edgeId)
            selectedEdgeId.value = null
            options.drawerOpen.value = false
            options.onChanged()
        }
    }

    function closeSelectionEditors(): void {
        selectedNodeId.value = null
        options.conditionSettingsOpen.value = false
        options.actionEditorOpen.value = false
        options.drawerOpen.value = false
        options.resetInspectorDraft()
    }

    function isPointInsideNodeShape(event: MouseEvent | TouchEvent, node: {type: string}): boolean {
        const target = event.target instanceof Element ? event.target : null

        if (target?.closest('.vue-flow__handle') || !['condition', 'end', 'scenario_link'].includes(node.type)) {
            return true
        }

        const element = target?.closest('.scenario-flow-node')
        if (!element || !(event instanceof MouseEvent)) {
            return true
        }

        const rect = element.getBoundingClientRect()
        const halfWidth = rect.width / 2
        const halfHeight = rect.height / 2
        const dx = Math.abs(event.clientX - rect.left - halfWidth)
        const dy = Math.abs(event.clientY - rect.top - halfHeight)

        if (node.type === 'condition') {
            return dx / halfWidth + dy / halfHeight <= 1
        }

        return (dx * dx) / (halfWidth * halfWidth) + (dy * dy) / (halfHeight * halfHeight) <= 1
    }

    function onNodeClick({event, node}: NodeMouseEvent): void {
        event.stopPropagation()

        if (isPointInsideNodeShape(event, node)) {
            selectedNodeId.value = node.id
            selectedEdgeId.value = null
        }
    }

    function onNodeDoubleClick({event, node}: NodeMouseEvent): void {
        event.stopPropagation()

        if (!isPointInsideNodeShape(event, node)) {
            return
        }

        selectedNodeId.value = node.id
        selectedEdgeId.value = null

        if (node.type === 'block') {
            options.openBlockEditor(node.id)
        } else if (node.type === 'action') {
            options.actionEditorOpen.value = true
        } else {
            options.drawerOpen.value = true
        }
    }

    function onEdgeClick({event, edge}: EdgeMouseEvent): void {
        event.stopPropagation()
        selectedEdgeId.value = edge.id
        selectedNodeId.value = null
        options.conditionSettingsOpen.value = false
        options.drawerOpen.value = false
        options.resetInspectorDraft()
    }

    function onPaneClick(event: MouseEvent): void {
        const target = event.target instanceof Element ? event.target : null

        if (target?.closest('.vue-flow__node, .vue-flow__edge')) {
            return
        }

        options.conditionSettingsOpen.value = false
        resetFlowSelection()
    }

    function updateConditionEdgeSetting(key: string, value: unknown): void {
        const edgeId = selectedEdge.value?.id

        if (!edgeId) {
            return
        }

        options.edges.value = options.edges.value.map((edge) => edge.id === edgeId
            ? {
                ...edge,
                label: key === 'value' ? String(value || '') || undefined : edge.label,
                data: {...(edge.data ?? {}), [key]: value},
            }
            : edge)
        options.onChanged()
    }

    function openSelectedConditionSettings(): void {
        if (selectedEdgeFromCondition.value) {
            options.conditionSettingsOpen.value = true
        }
    }

    function applyConditionLogicalVariable(variable: FlowLogicalVariable): void {
        updateConditionEdgeSetting('value', variable.value)
        copiedConditionVariableId.value = variable.id
        setTimeout(() => {
            copiedConditionVariableId.value = null
        }, 1500)
    }

    watch(selectedEdgeId, (id) => {
        for (const edge of options.edges.value) {
            const color = edge.id === id ? '#2563eb' : '#94a3b8'
            edge.style = {...(edge.style ?? {}), stroke: color}
            edge.markerEnd = {type: MarkerType.ArrowClosed, width: 18, height: 18, color}
        }
    })

    return {
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
        onEdgeClick,
        onPaneClick,
        updateConditionEdgeSetting,
        openSelectedConditionSettings,
        applyConditionLogicalVariable,
    }
}
