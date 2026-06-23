export type ActionType = string

export interface ActionConfigField {
    key: string
    label: string
    type: string
    required: boolean
    default: unknown
    placeholder: string | null
    description: string | null
    multiline: boolean
    options: Array<{ value: string, label: string }>
}

export interface ActionTypeMeta {
    value: string
    label: string
    default_code: string
    fields: ActionConfigField[]
}

export type InputFieldType = 'string' | 'number' | 'boolean' | 'uuid' | 'email'

export interface ActionInputField {
    key: string
    label: string
    type: InputFieldType
    required: boolean
    default: unknown
}

export interface ActionSchedule {
    id: number
    action_id: string
    enabled: boolean
    cron: string | null
    timezone: string
    input: Record<string, unknown> | null
    options: Record<string, unknown> | null
    settings: Record<string, unknown> | null
    last_run_at: string | null
    next_run_at: string | null
}

export interface ScheduleListItem {
    id: number
    enabled: boolean
    cron: string | null
    timezone: string
    last_run_at: string | null
    next_run_at: string | null
    action: {
        id: string
        name: string
        code: string
        type: string
        is_active: boolean
    } | null
}

export interface CronPreset {
    label: string
    cron: string
}

export const CRON_PRESETS: CronPreset[] = [
    { label: 'Каждую минуту', cron: '* * * * *' },
    { label: 'Каждый час',    cron: '0 * * * *' },
    { label: 'Ежедневно 9:00', cron: '0 9 * * *' },
    { label: 'По понедельникам 9:00', cron: '0 9 * * 1' },
    { label: '1-го числа месяца 9:00', cron: '0 9 1 * *' },
]

export interface ActionRun {
    id: number
    action_id: string
    action_name?: string | null
    action_key?: string | null
    input?: Record<string, unknown> | null
    output?: Record<string, unknown> | null
    status: string
    error?: string | null
    reason?: string | null
    attempts_count?: number
    duration_ms?: number | null
    started_at?: string | null
    finished_at?: string | null
}

export interface Action {
    id: string
    name: string
    key: string
    code: string
    description: string | null
    type: ActionType
    is_active: boolean
    config: Record<string, unknown> | null
    schema: Record<string, unknown> | null
    ui_schema: Record<string, unknown> | null
    input_fields: ActionInputField[]
    schedule: ActionSchedule | null
    created_at: string | null
    updated_at: string | null
}

export interface ActionPayload {
    name: string
    key: string
    code: string
    description: string | null
    type: ActionType
    is_active: boolean
    config: Record<string, unknown>
    schema?: Record<string, unknown>
    ui_schema?: Record<string, unknown>
    input_fields: ActionInputField[]
}

export interface SchedulePayload {
    enabled: boolean
    cron: string | null
    timezone: string
    input: Record<string, unknown>
    options: Record<string, unknown>
    settings: Record<string, unknown>
}
