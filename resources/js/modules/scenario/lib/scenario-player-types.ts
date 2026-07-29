export type ScenarioRunStatus = 'active' | 'completed' | 'failed'
export type ScenarioNodeType = 'start' | 'block' | 'condition' | 'end' | 'scenario_link'
export type ConditionMode = 'manual' | 'auto'

export interface SurveyBlock {
    id: string
    type: 'paragraph' | 'rich_text' | 'heading' | 'image' | 'button' | 'input' | 'email' | 'phone' | 'vin' | 'grz' | 'textarea' | 'number' | 'select' | 'date' | 'datetime' | 'checkbox' | 'hidden' | 'variable' | 'collapse' | 'container' | 'group' | 'directory_list' | 'directory_table' | 'suggest' | 'map_point' | 'route' | 'directory_map'
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
    layoutDocument?: unknown
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

export interface ActionStage {
    code: string
    name: string
}

export type ActionStageStatus = 'pending' | 'running' | 'success' | 'failed'

export interface ScenarioRenderedAction {
    type: 'action'
    data: Record<string, unknown>
    wait_for_result: boolean
    stages: ActionStage[]
    results: Record<string, ActionStageStatus>
    failed?: boolean
}

export interface ScenarioRunStep {
    id: number
    node_id: string
    node_type: ScenarioNodeType
    scenario_version_id?: string | null
    scenario_name?: string | null
    scenario_version_name?: string | null
    input: Record<string, unknown> | null
    output: Record<string, unknown> | null
    rendered: ScenarioRenderedBlock | ScenarioRenderedCondition | ScenarioRenderedEnd | Record<string, unknown> | null
    entered_at: string | null
    exited_at: string | null
}

export type RunHistoryEventType =
    | 'transition'
    | 'field_filled'
    | 'field_changed'
    | 'condition_evaluated'
    | 'action_completed'
    | 'action_failed'
    | 'scenario_link_followed'
    | 'run_started'
    | 'run_completed'
    | 'run_failed'
    | 'cancelled'

export interface RunHistoryEvent {
    at: string | null
    actor: string | null
    type: RunHistoryEventType
    node_id: string | null
    node_type: string | null
    node_title: string | null
    field_label?: string
    field_id?: string
    old_value?: unknown
    new_value?: unknown
    condition_mode?: 'manual' | 'auto'
    condition_label?: string | null
    target_node_id?: string | null
    action_id?: string
    action_name?: string | null
    code?: string
    output?: Record<string, unknown> | null
    error?: string | null
    target_scenario_id?: string
    target_scenario_name?: string | null
}

export interface ScenarioRunPayload {
    id: string
    number?: number | null
    number_formatted?: string | null
    scenario_id: string
    scenario_name?: string | null
    scenario_version_id: string
    scenario_version_name?: string | null
    current_scenario_name?: string | null
    root_scenario_version_id?: string | null
    root_scenario_name?: string | null
    root_scenario_version_name?: string | null
    scenario_version_created_at?: string | null
    created_at?: string | null
    current_node_id: string | null
    status: ScenarioRunStatus
    context: Record<string, unknown>
    current_node: ScenarioNode | null
    rendered: ScenarioRenderedBlock | ScenarioRenderedCondition | ScenarioRenderedEnd | Record<string, unknown> | null
    steps: ScenarioRunStep[]
    operator?: ScenarioRunParty | null
    client?: ScenarioRunClient | null
}

export interface ScenarioRunParty {
    id: number
    name?: string | null
    fio?: string | null
    login?: string | null
}

export interface ScenarioRunClient {
    fio?: string | null
    phone?: string | null
}

export interface ScenarioTimelineEntry {
    key: string
    node_id: string
    node_type: ScenarioNodeType | string
    status: 'past' | 'current'
    rendered: ScenarioRenderedBlock | ScenarioRenderedCondition | ScenarioRenderedEnd | Record<string, unknown> | null
    context: Record<string, unknown>
    scenario_version_id?: string | null
    scenario_name?: string | null
    scenario_version_name?: string | null
}

export interface ScenarioTimelineDivider {
    type: 'divider'
    key: string
    kind: 'start' | 'end'
    scenarioName: string
    versionName: string
}

export type ScenarioTimelineRow =
    | { type: 'entry'; entry: ScenarioTimelineEntry }
    | ScenarioTimelineDivider

export interface RunUpdatedMessage {
    type: 'run_updated'
    run: ScenarioRunPayload
}

export interface ActionStartedMessage {
    type: 'action_started'
    action_id: string
    code: string
}

export interface ActionCompletedMessage {
    type: 'action_completed'
    action_id: string
    code: string
    status: string
    output: Record<string, unknown> | null
}

export interface ActionFailedMessage {
    type: 'action_failed'
    action_id: string
    code: string
    error: string | null
}

export type ScenarioRunMessage =
    | RunUpdatedMessage
    | ActionStartedMessage
    | ActionCompletedMessage
    | ActionFailedMessage
