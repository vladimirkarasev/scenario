export interface FeedFolderItem {
    type: 'folder'
    id: string
    name: string
    parent_id: string | null
    children_count: number
    created_at: string | null
    updated_at: string | null
}

export interface FeedDirectoryItem {
    type: 'directory'
    id: string
    name: string
    slug: string
    description: string | null
    source_type: string
    sync_status: string
    versions_count: number
    created_at: string | null
    updated_at: string | null
}

export type DirectoryFeedRow = FeedFolderItem | FeedDirectoryItem

export interface DirectoryFeedResponse {
    data: DirectoryFeedRow[]
    pagination: {
        current_page: number
        last_page: number
        per_page: number
        total: number
        folders_total: number
        items_total: number
    }
}
