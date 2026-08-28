import type {
    EdgeMarkerType,
    NodeTypesObject,
    Styles,
    VueFlowStore,
    XYPosition,
} from '@vue-flow/core'
import type {
    NodeType,
    ScenarioBlockData,
    ScenarioFlowDocument,
    Viewport,
} from '@/modules/scenario/lib/scenario-flow-document'
import type {ScenarioType} from '@/modules/scenario/types/scenario'

export interface ScenarioFlowScenario {
    id: string
    name: string
}

export interface ScenarioFlowNode {
    id: string
    data: ScenarioBlockData
    type: NodeType
    position: XYPosition
    selected?: boolean
}

export interface ScenarioFlowEdge {
    id: string
    source: string
    target: string
    sourceHandle?: string | null
    targetHandle?: string | null
    data?: Record<string, unknown>
    label?: string
    selected?: boolean
    type?: string
    style?: Styles
    markerEnd?: EdgeMarkerType
    zIndex?: number
}

export type ScenarioFlowNodeTypes = NodeTypesObject
export type ScenarioFlowInstance = Pick<VueFlowStore, 'screenToFlowCoordinate' | 'setViewport'>

export interface ScenarioFlowEditorProps {
    modelValue: ScenarioFlowDocument
    scenarios: ScenarioFlowScenario[]
    editable: boolean
    scenarioType: ScenarioType
    scenarioId: string | null
    versionId: string | null
}

export interface ScenarioFlowState {
    nodes: ScenarioFlowNode[]
    edges: ScenarioFlowEdge[]
    viewport: Viewport
}

export interface ScenarioFlowCanvasExpose {
    nodePosition: (offset: number) => XYPosition
}

export interface ScenarioFlowEditorExpose {
    getDocument: () => ScenarioFlowDocument
    markSaved: () => void
}
