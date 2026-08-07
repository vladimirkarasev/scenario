export interface AuthUser {
    id: number
    name: string
    email: string
    project_id: string | null
    permissions: string[]
}

export interface AuthTokens {
    access_token: string
    refresh_token: string
}
