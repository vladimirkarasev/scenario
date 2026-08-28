import type {ScenarioStatus, ScenarioType} from '@/modules/scenario/types/scenario'

export interface FeedActor {
    id: number
    name: string | null
    fio: string | null
    login: string | null
}

export interface FeedFolderItem {
    type: 'folder'
    id: string
    name: string
    parent_id: string | null
    children_count: number
    created_at: string | null
    updated_at: string | null
}

export interface FeedScenarioItem {
    type: 'scenario'
    id: string
    name: string
    alias: string | null
    description: string | null
    status: ScenarioStatus
    scenario_type: ScenarioType
    tags: string[]
    versions_count: number
    active_version_id: string | null
    created_at: string | null
    updated_at: string | null
    created_by: FeedActor | null
    updated_by: FeedActor | null
}

export type ScenarioFeedRow = FeedFolderItem | FeedScenarioItem

export interface ScenarioFeedPagination {
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number | null
    to: number | null
    folders_total: number
    items_total: number
}

export interface ScenarioFeedCounts {
    all: number
    active: number
    draft: number
    archived: number
}

export interface ScenarioFeedResponse {
    data: ScenarioFeedRow[]
    meta: ScenarioFeedPagination & { counts_by_status: ScenarioFeedCounts }
}

export interface FeedFolder {
    id: string
    name: string
    parent_id: string | null
    parent_path: string
    path_ids: string[]
    children_count: number
}

export interface FeedScenario {
    id: string
    name: string
    status: ScenarioStatus
    scenario_type: ScenarioType
    folder_id: string | null
    folder_path: string
    active_version_id: string | null
}

export interface ScenarioFeedResult {
    folders: FeedFolder[]
    scenarios: FeedScenario[]
}
