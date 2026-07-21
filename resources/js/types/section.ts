export interface SectionCategory {
    id: string
    parent_id: string | null
    name: string
    is_active?: boolean
    is_system?: boolean
    is_workspace?: boolean
    children_count: number
    group_ids?: string[]
    created_at?: string | null
    updated_at?: string | null
}

export type SectionNode<T extends SectionCategory> = T & { children: SectionNode<T>[] }

export interface FlatSectionItem<T extends SectionCategory> {
    section: SectionNode<T>
    depth: number
    hasChildren: boolean
}
