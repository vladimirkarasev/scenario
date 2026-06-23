export type ScenarioRunStatus = 'active' | 'completed' | 'failed'
export type ScenarioNodeType = 'start' | 'block' | 'condition' | 'end' | 'scenario_link'
export type ConditionMode = 'manual' | 'auto'

export interface SurveyBlock {
    id: string
    type: 'paragraph' | 'rich_text' | 'heading' | 'image' | 'button' | 'input' | 'email' | 'phone' | 'textarea' | 'number' | 'select' | 'date' | 'datetime' | 'checkbox' | 'hidden' | 'variable' | 'collapse' | 'container' | 'group' | 'directory_list' | 'directory_table'
    props?: Record<string, unknown>
    children?: SurveyBlock[]
}

export interface ScenarioNode {
    id: string
    type: ScenarioNodeType
    data: Record<string, unknown>
}

export interface ScenarioRenderedBlock {
    type: 'block'
    title: string
    blocks: SurveyBlock[]
}

export interface ScenarioRenderedConditionOption {
    label: string
    targetNodeId: string
}

export interface ScenarioRenderedCondition {
    type: 'condition'
    mode: ConditionMode
    question: string
    options: ScenarioRenderedConditionOption[]
    expression?: string | null
}

export interface ScenarioRenderedEnd {
    type: 'end'
    title: string
    description: string
    blocks: SurveyBlock[]
}

export interface ScenarioRunStep {
    id: number
    node_id: string
    node_type: ScenarioNodeType
    input: Record<string, unknown> | null
    output: Record<string, unknown> | null
    rendered: ScenarioRenderedBlock | ScenarioRenderedCondition | ScenarioRenderedEnd | Record<string, unknown> | null
    entered_at: string | null
    exited_at: string | null
}

export interface ScenarioRunPayload {
    id: string
    number?: number | null
    number_formatted?: string | null
    scenario_id: string
    scenario_name?: string | null
    scenario_version_id: string
    scenario_version_name?: string | null
    scenario_version_created_at?: string | null
    created_at?: string | null
    current_node_id: string | null
    status: ScenarioRunStatus
    context: Record<string, unknown>
    current_node: ScenarioNode | null
    rendered: ScenarioRenderedBlock | ScenarioRenderedCondition | ScenarioRenderedEnd | Record<string, unknown> | null
    steps: ScenarioRunStep[]
}

export interface ScenarioTimelineEntry {
    key: string
    node_id: string
    node_type: ScenarioNodeType | string
    status: 'past' | 'current'
    rendered: ScenarioRenderedBlock | ScenarioRenderedCondition | ScenarioRenderedEnd | Record<string, unknown> | null
    context: Record<string, unknown>
}
