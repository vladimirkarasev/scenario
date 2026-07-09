import {getJson} from '@/lib/http'
import type {ScenarioStatus} from '@/modules/scenario/types/scenario'

interface FeedActor {
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
    meta: ScenarioFeedPagination & {
        counts_by_status: ScenarioFeedCounts
    }
}

export const scenarioFeedRepository = {
    async fetch(qs: URLSearchParams): Promise<ScenarioFeedResponse> {
        return getJson<ScenarioFeedResponse>(
            `/api/scenarios/feed?${qs.toString()}`,
            'Не удалось загрузить сценарии.',
        )
    },
}
