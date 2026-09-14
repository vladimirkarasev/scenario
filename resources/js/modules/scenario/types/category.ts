export interface CategoryRef {
    id: string
    parent_id: string | null
    name: string
    is_active: boolean
    is_workspace?: boolean
    children_count: number
    group_ids?: string[]
    created_at?: string | null
    updated_at?: string | null
}

export interface CategoryOption {
    id: string
    name: string
    parent_id: string | null
}

export interface ScenarioCategoryPayload {
    name: string
    parent_id: string | null
    is_active: boolean
    group_ids?: string[]
    inherit_to_descendants?: boolean
    is_workspace?: boolean
}
