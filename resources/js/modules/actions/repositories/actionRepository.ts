import {destroyJson, getJson, sendJson} from '@/lib/http'
import type {
    Action,
    ActionInputField,
    ActionPayload,
    ActionSchedule,
    ActionType,
    InputFieldType
} from '@/modules/actions/types/action'

interface JsonApiItem {
    id: string
    attributes: Record<string, unknown>
    relationships?: Record<string, { data?: unknown }>
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value)
}

function stringValue(value: unknown): string {
    return typeof value === 'string' || typeof value === 'number' ? String(value) : ''
}

function nullableString(value: unknown): string | null {
    const s = stringValue(value)
    return s === '' ? null : s
}

function objectValue(value: unknown): Record<string, unknown> | null {
    return isRecord(value) ? value : null
}

function numberValue(value: unknown): number {
    return typeof value === 'number' ? value : Number(value || 0)
}

function numberListOrNull(value: unknown): number[] | null {
    return Array.isArray(value) ? value.filter((n): n is number => typeof n === 'number') : null
}

function normalizeSchedule(value: unknown): ActionSchedule | null {
    if (!isRecord(value)) return null

    return {
        id: numberValue(value.id),
        action_id: stringValue(value.action_id),
        enabled: Boolean(value.enabled),
        cron: nullableString(value.cron),
        timezone: stringValue(value.timezone) || 'Europe/Moscow',
        input: objectValue(value.input),
        options: objectValue(value.options),
        settings: objectValue(value.settings),
        last_run_at: nullableString(value.last_run_at),
        next_run_at: nullableString(value.next_run_at),
    }
}

function normalizeInputFields(value: unknown): ActionInputField[] {
    if (!Array.isArray(value)) return []
    const result: ActionInputField[] = []
    for (const item of value) {
        if (!isRecord(item)) continue
        const key = stringValue(item.key)
        if (!key) continue
        result.push({
            key,
            label: stringValue(item.label) || key,
            type: (stringValue(item.type) || 'string') as InputFieldType,
            required: Boolean(item.required),
            default: item.default,
        })
    }
    return result
}

export function normalizeAction(item: JsonApiItem): Action {
    const a = item.attributes
    const scheduleRaw = item.relationships?.schedule?.data

    return {
        id: String(item.id),
        name: stringValue(a.name),
        slug: stringValue(a.slug),
        code: stringValue(a.code),
        description: nullableString(a.description),
        type: (stringValue(a.type) || 'template_file') as ActionType,
        is_active: Boolean(a.is_active),
        config: objectValue(a.config),
        schema: objectValue(a.schema),
        ui_schema: objectValue(a.ui_schema),
        input_fields: normalizeInputFields(a.input_fields),
        default_backoff: numberListOrNull(a.default_backoff),
        category_ids: Array.isArray(a.category_ids) ? a.category_ids.map(String) : [],
        schedule: normalizeSchedule(scheduleRaw),
        created_at: nullableString(a.created_at),
        updated_at: nullableString(a.updated_at),
    }
}

export const actionRepository = {
    async list(qs: URLSearchParams): Promise<Action[]> {
        const payload = await getJson<{ data: JsonApiItem[] }>(
            `/api/actions?${qs}`,
            'Не удалось загрузить actions.',
        )
        return (payload.data ?? []).map(normalizeAction)
    },

    async find(id: string): Promise<Action> {
        const payload = await getJson<{ data: JsonApiItem }>(
            `/api/actions/${id}`,
            'Не удалось загрузить action.',
        )
        return normalizeAction(payload.data)
    },

    async create(body: ActionPayload): Promise<Action> {
        const payload = await sendJson<{ data: JsonApiItem }>('/api/actions', {
            method: 'POST',
            body,
            fallbackMessage: 'Не удалось создать action.',
        })
        return normalizeAction(payload.data)
    },

    async update(id: string, body: ActionPayload): Promise<Action> {
        const payload = await sendJson<{ data: JsonApiItem }>(`/api/actions/${id}`, {
            method: 'PUT',
            body,
            fallbackMessage: 'Не удалось сохранить action.',
        })
        return normalizeAction(payload.data)
    },

    async remove(id: string): Promise<void> {
        await destroyJson(`/api/actions/${id}`, 'Не удалось удалить action.')
    },

    async run(code: string, id: string, input: Record<string, unknown>): Promise<void> {
        await sendJson('/api/actions/run', {
            body: {
                mode: 'sequential',
                actions: {[code]: id},
                input,
            },
            fallbackMessage: 'Не удалось запустить action.',
        })
    },
}
