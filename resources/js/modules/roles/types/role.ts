export interface Role {
  id: number
  name: string
  title: string | null
  description: string | null
  is_system: boolean
  users_count: number
  permissions: string[]
}

export interface PermissionOption {
  name: string
  label: string
  group: string
}

export interface RolePayload {
  name: string
  title: string | null
  description: string | null
  permissions: string[]
}
