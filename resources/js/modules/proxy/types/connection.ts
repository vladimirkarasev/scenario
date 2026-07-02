import type {WebhookField} from '@/modules/proxy/types/webhook'

/** Запись доступа (connection): «куда + как авторизуемся», переиспользуется эндпоинтами. */
export interface ProxyConnection {
    id: number
    project_id: string | null
    name: string
    credential_type: string
    credential_label: string
    config: Record<string, unknown>
    secret_filled: Record<string, boolean>
    updated_at: string | null
}

/** Тип доступа (драйвер) со схемой полей — для формы и пикера. */
export interface CredentialType {
    type: string
    label: string
    group: string
    fields: WebhookField[]
}

export interface ProxyConnectionPayload {
    name: string
    credential_type: string
    values: Record<string, unknown>
}
