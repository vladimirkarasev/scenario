import type {PaginationMeta} from '@/types/pagination'

export interface Group {
    id: string
    name: string
    slug: string
    ext_id: string | null
    description: string | null
    is_active: boolean
    members_count: number
    created_at: string | null
}

export interface GroupMember {
    id: string
    name: string
    email: string
    login: string | null
}

export interface GroupsPage {
    data: Group[]
    meta: PaginationMeta
}

export interface GroupPayload {
    name: string
    slug: string
    description: string
    is_active: boolean
    ext_id?: string | null
}
