import {nextTick, ref, watch, type Ref} from 'vue'
import {
    fromVueFlowState,
    normalizeScenarioFlowDocument,
    toVueFlowState,
    type ScenarioFlowDocument,
    type Viewport,
} from '@/modules/scenario/lib/scenario-flow-document'
import type {
    ScenarioFlowEdge,
    ScenarioFlowNode,
    ScenarioFlowScenario,
} from '@/modules/scenario/types/scenario-flow-editor'

export type EditorTab = 'editor' | 'json'

interface ScenarioFlowDocumentOptions {
    modelValue: () => ScenarioFlowDocument
    scenarios: () => ScenarioFlowScenario[]
    nodes: Ref<ScenarioFlowNode[]>
    edges: Ref<ScenarioFlowEdge[]>
    viewport: Ref<Viewport>
    resetSelection: () => void
    onDirtyChange: (dirty: boolean) => void
    onFlowStateApplied?: () => void
}

export function useScenarioFlowDocument(options: ScenarioFlowDocumentOptions) {
    const draggingNode = ref(false)
    const dirty = ref(false)
    const schemaPreview = ref('')
    const activeRightTab = ref<EditorTab>('editor')

    function currentFlowDocument(): ScenarioFlowDocument {
        return fromVueFlowState({
            nodes: options.nodes.value,
            edges: options.edges.value,
            viewport: options.viewport.value,
        })
    }

    function buildSchemaPreview(): string {
        const document = currentFlowDocument()

        return JSON.stringify({
            format: document.format,
            version: document.version,
            viewport: document.viewport,
            blocks: document.blocks,
            edges: options.edges.value.map((edge) => ({
                id: edge.id,
                source: edge.source,
                sourceHandle: edge.sourceHandle ?? null,
                target: edge.target,
                targetHandle: edge.targetHandle ?? null,
                label: edge.label ?? null,
                data: edge.data ?? {},
            })),
        }, null, 2)
    }

    function updateSchemaPreview(): void {
        if (activeRightTab.value === 'json') {
            schemaPreview.value = buildSchemaPreview()
        }
    }

    function switchToJsonTab(): void {
        activeRightTab.value = 'json'
        schemaPreview.value = buildSchemaPreview()
    }

    function markChanged(): void {
        if (!dirty.value) {
            dirty.value = true
            options.onDirtyChange(true)
        }

        updateSchemaPreview()
    }

    function markSaved(): void {
        dirty.value = false
        options.onDirtyChange(false)
    }

    function onNodeDragStart(): void {
        draggingNode.value = true
    }

    function onNodeDragStop(): void {
        draggingNode.value = false
        markChanged()
    }

    function applyFlowState(document: ScenarioFlowDocument): void {
        const flowState = toVueFlowState(document, options.scenarios())
        options.nodes.value = flowState.nodes
        options.edges.value = flowState.edges
        options.viewport.value = flowState.viewport
        options.resetSelection()
        options.onFlowStateApplied?.()
    }

    function applyJsonEdit(value: unknown): void {
        applyFlowState(normalizeScenarioFlowDocument(value))

        void nextTick(markChanged)
    }

    watch(
        options.modelValue,
        (value) => {
            applyFlowState(value)
            markSaved()
        },
        {immediate: true},
    )

    watch(
        options.scenarios,
        (scenarios) => {
            const scenarioById = new Map(scenarios.map((scenario) => [scenario.id, scenario.name]))

            options.nodes.value = options.nodes.value.map((node) => node.type === 'scenario_link'
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
            options.onFlowStateApplied?.()
        },
    )

    return {
        activeRightTab,
        draggingNode,
        dirty,
        schemaPreview,
        currentFlowDocument,
        markChanged,
        markSaved,
        onNodeDragStart,
        onNodeDragStop,
        switchToJsonTab,
        applyJsonEdit,
    }
}
