import type {ScenarioType} from '@/modules/scenario/types/scenario'

export interface SystemVariableField {
    suffix: string
    label: string
    description: string
}

export interface SystemVariableGroup {
    name: string
    label: string
    fields: SystemVariableField[]
}

export interface ScenarioSystemVariableCatalog {
    type: ScenarioType
    groups: SystemVariableGroup[]
}
