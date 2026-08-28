import type {NodeType} from '@/modules/scenario/lib/scenario-flow-document'
import type {ScenarioType} from '@/modules/scenario/types/scenario'

export interface ScenarioNodeDefinition {
    type: NodeType
    label: string
    icon: string
}

export interface ScenarioNodeCatalog {
    type: ScenarioType
    nodes: ScenarioNodeDefinition[]
}
