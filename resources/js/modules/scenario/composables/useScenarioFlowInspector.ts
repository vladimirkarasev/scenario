import {reactive, ref, watch, type ComputedRef, type Ref} from 'vue'
import {
    cloneScenarioFlowDocument,
    normalizeScenarioFlowDocument,
    type ScenarioBlockData,
} from '@/modules/scenario/lib/scenario-flow-document'
import type {
    ScenarioFlowNode,
    ScenarioFlowScenario,
} from '@/modules/scenario/types/scenario-flow-editor'

interface ScenarioFlowInspectorOptions {
    nodes: Ref<ScenarioFlowNode[]>
    selectedNode: ComputedRef<ScenarioFlowNode | null>
    drawerOpen: Ref<boolean>
    scenarios: () => ScenarioFlowScenario[]
    onChanged: () => void
}

export function useScenarioFlowInspector(options: ScenarioFlowInspectorOptions) {
    const inspectorDraft = reactive<Record<string, unknown>>({})
    const inspectorSnapshot = ref<ScenarioBlockData | null>(null)

    function resetInspectorDraft(): void {
        Object.keys(inspectorDraft).forEach((key) => {
            delete inspectorDraft[key]
        })
    }

    function commitInspector(): void {
        options.drawerOpen.value = false
    }

    function cancelInspector(): void {
        const node = options.selectedNode.value

        if (!node || !inspectorSnapshot.value) {
            options.drawerOpen.value = false
            return
        }

        const snapshot = inspectorSnapshot.value
        options.nodes.value = options.nodes.value.map((item) => item.id === node.id
            ? {...item, data: JSON.parse(JSON.stringify(snapshot)) as ScenarioBlockData}
            : item)
        options.drawerOpen.value = false
    }

    function syncSelectedNode(): void {
        const selectedId = options.selectedNode.value?.id

        if (!selectedId) {
            return
        }

        options.nodes.value = options.nodes.value.map((node) => node.id !== selectedId
            ? node
            : {
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
                        ?? options.scenarios().find((scenario) => scenario.id === inspectorDraft.targetScenarioId)?.name
                        ?? null
                        : null,
                    targetVersionName: inspectorDraft.targetVersionId
                        ? inspectorDraft.targetVersionName ?? null
                        : null,
                },
            })
        options.onChanged()
    }

    function updateBlockVariable(value: unknown): void {
        inspectorDraft.variable = String(value ?? '').replace(/\s+/g, '_')
        syncSelectedNode()
    }

    watch(
        () => options.selectedNode.value?.id ?? null,
        () => {
            const node = options.selectedNode.value

            if (!node) {
                resetInspectorDraft()
                inspectorSnapshot.value = null
                return
            }

            const document = cloneScenarioFlowDocument({
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
            })

            Object.assign(inspectorDraft, document.blocks[0].data)
            inspectorSnapshot.value = JSON.parse(JSON.stringify(node.data)) as ScenarioBlockData
        },
    )

    return {
        inspectorDraft,
        resetInspectorDraft,
        commitInspector,
        cancelInspector,
        syncSelectedNode,
        updateBlockVariable,
    }
}
