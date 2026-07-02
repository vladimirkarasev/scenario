import {getJson} from '@/lib/http'

export interface FeedFolderRow {
    type: 'folder'
    id: string
    name: string
    parent_id: string | null
    is_system: boolean
    children_count: number
    created_at: string | null
    updated_at: string | null
}

export interface FeedEndpointRow {
    type: 'endpoint'
    id: number
    uuid: string
    name: string
    code: string
    endpoint_type: string
    method: string | null
    is_active: boolean
    is_mocked: boolean
    base_uri: string | null
    description: string | null
    created_at: string | null
    updated_at: string | null
}

export type ProxyFeedRow = FeedFolderRow | FeedEndpointRow

export interface ProxyFeedResponse {
    data: ProxyFeedRow[]
    pagination: {
        current_page: number
        last_page: number
        per_page: number
        total: number
        folders_total: number
        items_total: number
    }
}

export const proxyFeedRepository = {
    async fetch(qs: URLSearchParams): Promise<ProxyFeedResponse> {
        return getJson<ProxyFeedResponse>(
            `/api/proxy/feed?${qs.toString()}`,
            'Не удалось загрузить интеграции.',
        )
    },
}
