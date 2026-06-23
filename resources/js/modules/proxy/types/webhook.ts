import type { PaginationMeta } from '@/types/pagination'

export interface MockResponseVariant {
  name?: string | null
  status: number
  body?: Record<string, unknown> | unknown[] | null
  headers?: Record<string, string> | null
  match?: Record<string, unknown> | null
}

export interface WebhookEndpoint {
  id: number
  uuid: string
  name: string
  code: string
  description: string | null
  is_active: boolean
  is_mocked: boolean
  handler_class: string
  config: Record<string, unknown> | unknown[]
  mock_responses: MockResponseVariant[]
  updated_at: string | null
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
}

export interface WebhookPayload {
  name: string
  code: string
  description: string | null
  is_active: boolean
  is_mocked: boolean
  handler_class: string
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
