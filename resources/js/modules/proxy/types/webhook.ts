import type {PaginationMeta} from '@/types/pagination'

export interface MockResponseVariant {
    name?: string | null
    status: number
    body?: Record<string, unknown> | unknown[] | null
    headers?: Record<string, string> | null
    is_active?: boolean
}

export interface WebhookEndpoint {
    id: number
    project_id?: string | null
    uuid: string
    name: string
    code: string
    type: string
    method: string | null
    description: string | null
    is_active: boolean
    is_mocked: boolean
    handler_class: string
    connection_id: number | null
    category_ids: string[]
    base_uri: string | null
    credentials: Record<string, unknown>
    secret_filled: Record<string, boolean>
    receive_url: string
    config: Record<string, unknown> | unknown[]
    mock_responses: MockResponseVariant[]
    updated_at: string | null
}

export interface ProxyCategory {
    id: string
    parent_id: string | null
    name: string
    is_active: boolean
    is_system: boolean
    children_count: number
    created_at?: string | null
    updated_at?: string | null
}

export interface HandlerOption {
    class: string
    label: string
    group: string
    credential_type: string | null
    method: string
}

export interface WebhookField {
    key: string
    label: string
    source: string
    type: string
    required: boolean
    nullable: boolean
    default: unknown
    example: string | null
    description: string | null
    secret?: boolean
    filterable?: boolean
    filter_key?: string | null
    identity?: boolean
    values?: string[]
}

export interface WebhookPayload {
    name: string
    code: string
    type: string
    description: string | null
    is_active: boolean
    is_mocked: boolean
    handler_class: string
    connection_id?: number | null
    category_ids?: string[]
    config: Record<string, unknown> | unknown[]
    mock_responses: MockResponseVariant[]
}

export interface WebhookRequestLog {
    id: string
    request_id: string
    endpoint: string | null
    endpoint_id: number | null
    status: string
    status_label: string
    status_color: string
    is_mocked: boolean
    ip: string | null
    error: string | null
    created_at: string | null
}

export interface WebhookRequestLogDetail extends WebhookRequestLog {
    method: string | null
    path: string | null
    user_agent: string | null
    received_at: string | null
    processed_at: string | null
    payload: Record<string, unknown> | null
    query_params: Record<string, unknown> | null
    headers_masked: Record<string, unknown> | null
    normalized_data: Record<string, unknown> | null
    message_box: Record<string, unknown> | null
    response_code: number | null
    response_headers: Record<string, unknown> | null
    response_body: unknown
}

export interface WebhookRequestPage {
    data: WebhookRequestLog[]
    meta: PaginationMeta
}
