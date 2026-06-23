import type { PaginationMeta } from '@/types/pagination'

export interface RoleRef { id: string; name: string; title: string | null }
export interface GroupRef { id: string; name: string; slug: string }

export interface User {
  id: string
  name: string
  fio: string | null
  email: string
  login: string | null
  external_id: string | null
  created_at: string | null
  groups: GroupRef[]
  roles: RoleRef[]
}

export interface UsersPage {
  data: User[]
  meta: PaginationMeta
}

export interface ApiToken {
  id: number
  name: string
  abilities: string[]
  last_used_at: string | null
  created_at: string | null
  expires_at: string | null
}

export interface ApiTokenCreated extends ApiToken {
  plain_text_token: string
}

export interface UserPayload {
  name: string
  email: string
  fio?: string
  login?: string
  external_id?: string
  password?: string
  roles?: string[]
  group_ids?: string[]
}
