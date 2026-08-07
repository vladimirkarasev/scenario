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

export interface FeedActionRow {
    type: 'action'
    id: string
    name: string
    slug: string
    action_type: string
    action_type_label: string
    is_active: boolean
    description: string | null
    schedule: {enabled: boolean; cron: string | null; next_run_at: string | null} | null
    created_at: string | null
    updated_at: string | null
}

export type ActionFeedRow = FeedFolderRow | FeedActionRow

export interface ActionFeedMeta {
    current_page: number
    last_page: number
    per_page: number
    total: number
    folders_total: number
    items_total: number
}

export interface ActionFeedResponse {
    data: ActionFeedRow[]
    pagination: ActionFeedMeta
}
