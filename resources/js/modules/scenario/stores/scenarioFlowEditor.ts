import {computed, nextTick, reactive, ref} from 'vue'
import {defineStore} from 'pinia'
import {addEdge, type Connection, type Edge, type GraphNode, type ViewportTransform} from '@vue-flow/core'
import {
    cloneScenarioFlowDocument,
    createScenarioFlowNode,
    fromVueFlowState,
    normalizeScenarioFlowDocument,
    stringifyScenarioFlowDocument,
    toVueFlowState,
    type NodeType,
    type ScenarioFlowDocument,
} from '@/modules/scenario/lib/scenario-flow-document'

interface Scenario {
    id: string
    name: string
}

export const useScenarioFlowEditorStore = defineStore('scenarioFlowEditor', () => {
    const scenarios = ref<Scenario[]>([])
    const editable = ref(false)
    const nodes = ref<GraphNode[]>([])
    const edges = ref<Edge[]>([])
    const viewport = ref<ViewportTransform>({x: 0, y: 0, zoom: 1})
    const selectedNodeId = ref<string | null>(null)
    const selectedEdgeId = ref<string | null>(null)
    const syncingFromModel = ref(false)
    const inspectorDraft = reactive<Record<string, unknown>>({})

    const selectedNode = computed<GraphNode | null>(() => (nodes.value as GraphNode[]).find((node) => node.id === selectedNodeId.value) ?? null)
    const selectedEdge = computed<Edge | null>(() => (edges.value as Edge[]).find((edge) => edge.id === selectedEdgeId.value) ?? null)
    const document = computed(() => fromVueFlowState({
        nodes: nodes.value,
        edges: edges.value,
        viewport: viewport.value,
    }))
    const serializedDocument = computed(() => stringifyScenarioFlowDocument(document.value))

    function resetInspectorDraft(): void {
        Object.keys(inspectorDraft).forEach((key) => {
            delete inspectorDraft[key]
        })
    }

    async function setDocument(value: ScenarioFlowDocument): Promise<void> {
        syncingFromModel.value = true

        const flowState = toVueFlowState(value, scenarios.value)
        nodes.value = flowState.nodes as GraphNode[]
        edges.value = flowState.edges as Edge[]
        viewport.value = flowState.viewport
        selectedNodeId.value = null
        selectedEdgeId.value = null
        resetInspectorDraft()

        await nextTick()
        syncingFromModel.value = false
    }

    function setScenarios(nextScenarios: Scenario[]): void {
        scenarios.value = nextScenarios

        const scenarioById = new Map(nextScenarios.map((scenario) => [scenario.id, scenario.name]))

        nodes.value = (nodes.value as GraphNode[]).map((node) => node.type === 'scenario_link'
            ? {
                ...node,
                data: {
                    ...node.data,
                    targetScenarioName: node.data.targetScenarioId
                        ? scenarioById.get(node.data.targetScenarioId) ?? null
                        : null,
                },
            }
            : node) as GraphNode[]
    }

    function setEditable(value: boolean): void {
        editable.value = value
    }

    function syncInspectorFromSelectedNode(): void {
        if (!selectedNode.value) {
            resetInspectorDraft()
            return
        }

        Object.assign(inspectorDraft, cloneScenarioFlowDocument({
            format: 'scenario-flow',
            version: 1,
            viewport: {x: 0, y: 0, zoom: 1},
            blocks: [{
                id: selectedNode.value.id,
                type: selectedNode.value.type as NodeType,
                position: selectedNode.value.position,
                data: selectedNode.value.data,
            }],
            connections: [],
        }).blocks[0].data)
    }

    function syncSelectedNode(): void {
        if (!selectedNode.value) {
            return
        }

        nodes.value = (nodes.value as GraphNode[]).map((node) => {
            if (node.id !== selectedNode.value!.id) {
                return node
            }

            return {
                ...node,
                data: {
                    ...node.data,
                    ...normalizeScenarioFlowDocument({
                        format: 'scenario-flow',
                        version: 1,
                        viewport: {x: 0, y: 0, zoom: 1},
                        blocks: [{
                            id: node.id,
                            type: node.type as NodeType,
                            position: node.position,
                            data: inspectorDraft,
                        }],
                        connections: [],
                    }).blocks[0].data,
                    targetScenarioName: inspectorDraft.targetScenarioId
                        ? scenarios.value.find((scenario) => scenario.id === inspectorDraft.targetScenarioId)?.name ?? null
                        : null,
                },
            }
        }) as GraphNode[]
    }

    function addNode(type: NodeType): void {
        if (!editable.value) {
            return
        }

        const offset = nodes.value.length * 36
        const block = createScenarioFlowNode(type, {
            x: 120 + (offset % 220),
            y: 120 + offset,
        })

        nodes.value = [
            ...(nodes.value as GraphNode[]),
            {
                id: block.id,
                type: block.type,
                position: block.position,
                data: block.data,
            } as GraphNode,
        ]
        selectedNodeId.value = block.id
        selectedEdgeId.value = null
        syncInspectorFromSelectedNode()
    }

    function addBlockField(): void {
        inspectorDraft.fields = [
            ...((inspectorDraft.fields as unknown[]) ?? []),
            {
                id: `field_${Date.now()}`,
                label: '',
                value: '',
            },
        ]

        syncSelectedNode()
    }

    function removeBlockField(fieldId: string): void {
        inspectorDraft.fields = ((inspectorDraft.fields as Array<{
            id: string
        }>) ?? []).filter((field) => field.id !== fieldId)
        syncSelectedNode()
    }

    function deleteSelected(): void {
        if (!editable.value) {
            return
        }

        if (selectedNode.value) {
            const nodeId = selectedNode.value.id
            nodes.value = (nodes.value as GraphNode[]).filter((node) => node.id !== nodeId) as GraphNode[]
            edges.value = (edges.value as Edge[]).filter((edge) => edge.source !== nodeId && edge.target !== nodeId) as Edge[]
            selectedNodeId.value = null
            resetInspectorDraft()
            return
        }

        if (selectedEdge.value) {
            edges.value = (edges.value as Edge[]).filter((edge) => edge.id !== selectedEdge.value!.id) as Edge[]
            selectedEdgeId.value = null
        }
    }

    function onConnect(connection: Connection): void {
        if (!editable.value) {
            return
        }

        edges.value = addEdge({
            ...connection,
            id: `edge_${Date.now()}`,
            type: 'smoothstep',
        }, edges.value as Edge[]) as Edge[]
    }

    function onNodeClick({node}: { node: GraphNode }): void {
        selectedNodeId.value = node.id
        selectedEdgeId.value = null
        syncInspectorFromSelectedNode()
    }

    function onEdgeClick({edge}: { edge: Edge }): void {
        selectedEdgeId.value = edge.id
        selectedNodeId.value = null
        resetInspectorDraft()
    }

    function onPaneClick(): void {
        selectedNodeId.value = null
        selectedEdgeId.value = null
        resetInspectorDraft()
    }

    function onPaneReady(instance: { setViewport: (v: ViewportTransform) => void }): void {
        instance.setViewport(viewport.value)
    }

    function onViewportChangeEnd(nextViewport: ViewportTransform): void {
        viewport.value = nextViewport
    }

    function updateEdgeLabel(value: string): void {
        if (!selectedEdge.value) {
            return
        }

        edges.value = (edges.value as Edge[]).map((edge) => edge.id === selectedEdge.value!.id
            ? {...edge, label: value || null}
            : edge) as Edge[]
    }

    return {
        scenarios,
        editable,
        nodes,
        edges,
        viewport,
        selectedNodeId,
        selectedEdgeId,
        syncingFromModel,
        inspectorDraft,
        selectedNode,
        selectedEdge,
        document,
        serializedDocument,
        setDocument,
        setScenarios,
        setEditable,
        syncSelectedNode,
        addNode,
        addBlockField,
        removeBlockField,
        deleteSelected,
        onConnect,
        onNodeClick,
        onEdgeClick,
        onPaneClick,
        onPaneReady,
        onViewportChangeEnd,
        updateEdgeLabel,
    }
})
