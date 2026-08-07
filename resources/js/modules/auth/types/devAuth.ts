export interface DevAuthUserOption {
    id: number
    name: string
    login: string | null
    email: string
    roles: string[]
}

export interface DevAuthProjectOption {
    id: string
    name: string
    host: string | null
    users: DevAuthUserOption[]
}

export interface DevAuthPageProps {
    projects: DevAuthProjectOption[]
    defaultProjectId: string | null
    defaultUserId: number | null
}

export interface DevAuthLaunchToken {
    token: string
    expiresIn: number
}
