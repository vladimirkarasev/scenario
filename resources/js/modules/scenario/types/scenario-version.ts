import type {PaginationMeta} from '@/types/pagination'

export type ScenarioVersionStatus = 'active' | 'draft' | 'archived'

export interface ScenarioVersionRevision {
    id: string
    created_at: string | null
}

export interface ScenarioVersion {
    id: string
    scenario_id: string
    name: string | null
    status: ScenarioVersionStatus
    schema_json: Record<string, unknown>
    created_at: string | null
    updated_at: string | null
    revisions: ScenarioVersionRevision[]
}

export interface ScenarioVersionPayload {
    name?: string | null
    status?: ScenarioVersionStatus
    schema_json: Record<string, unknown>
}

export interface ScenarioVersionSettingsPayload {
    name: string | null
    status: ScenarioVersionStatus
}

export interface ScenarioVersionHistoryPage {
    data: ScenarioVersionRevision[]
    meta: PaginationMeta
}
