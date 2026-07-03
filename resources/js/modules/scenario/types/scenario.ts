export interface ScenarioCategory {
    id: string
    parent_id: string | null
    name: string
    is_active: boolean
    children_count: number
    group_ids?: string[]
    created_at?: string | null
    updated_at?: string | null
}

export interface GroupRef {
    id: string
    name: string
}

export interface ScenarioVersionRef {
    id: string
    name: string | null
    status: string | null
    schema_version: number | null
    created_at: string | null
    updated_at: string | null
}

export interface ScenarioActor {
    id: number
    name: string | null
    login: string | null
    fio: string | null
}

export type ScenarioStatus = 'draft' | 'active' | 'archived'

export interface Scenario {
    id: string
    name: string
    description: string | null
    is_active: boolean
    status: ScenarioStatus
    alias: string | null
    tags: string[]
    active_version_id: string | null
    created_at: string | null
    updated_at: string | null
    created_by: ScenarioActor | null
    updated_by: ScenarioActor | null
    categories: ScenarioCategory[]
    groups: GroupRef[]
    versions: ScenarioVersionRef[]
}

export interface ScenariosPage {
    data: Scenario[]
    meta: {
        current_page: number
        last_page: number
        per_page: number
        total: number
    }
    includedCategories: ScenarioCategory[]
}

export interface ScenarioPayload {
    name: string
    description?: string | null
    is_active?: boolean
    alias?: string | null
    tags?: string[]
    active_version_id?: string | null
    category_ids?: string[]
    group_ids?: string[]
}

export type ScenarioInputFieldType = 'datetime' | 'json' | 'text' | 'boolean'

export interface ScenarioInputField {
    key: string
    label: string
    type: ScenarioInputFieldType
}

export interface ScenarioRunListItem {
    id: string
    number: number | null
    number_formatted: string | null
    scenario_id: string
    scenario_name: string | null
    scenario_version_id: string
    scenario_version_name: string | null
    scenario_version_created_at: string | null
    current_node_id: string | null
    status: string
    created_by: ScenarioActor | null
    updated_by: ScenarioActor | null
    created_at: string | null
    updated_at: string | null
}

export interface ScenarioRunStats {
    total: number
    active: number
    completed: number
    failed: number
}

export interface ScenarioRunsPage {
    data: ScenarioRunListItem[]
    meta: {
        current_page: number
        last_page: number
        per_page: number
        total: number
        from: number | null
        to: number | null
        stats: ScenarioRunStats
    }
}
