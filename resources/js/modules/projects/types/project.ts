import type { PaginationMeta } from '@/types/pagination'

export interface Project {
  id: string
  name: string
  sitekey: string
  host: string
  shared_secret: string | null
  is_active: boolean
  created_at: string | null
}

export interface ProjectsPage {
  data: Project[]
  meta: PaginationMeta
}

export interface ProjectPayload {
  name: string
  sitekey: string
  host: string
  shared_secret: string
  is_active: boolean
}
