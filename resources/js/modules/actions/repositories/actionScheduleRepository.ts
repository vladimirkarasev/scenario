import {destroyJson, getJson, sendJson} from '@/lib/http'
import type {ActionSchedule, ScheduleListItem, SchedulePayload} from '@/modules/actions/types/action'

interface JsonApiItem {
    id: string
    attributes: Record<string, unknown>
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value)
}

function objectValue(value: unknown): Record<string, unknown> | null {
    return isRecord(value) ? value : null
}

function stringValue(value: unknown): string {
    return typeof value === 'string' || typeof value === 'number' ? String(value) : ''
}

function nullableString(value: unknown): string | null {
    const s = stringValue(value)
    return s === '' ? null : s
}

function numberValue(value: unknown): number {
    return typeof value === 'number' ? value : Number(value || 0)
}

function normalize(item: JsonApiItem): ActionSchedule {
    const a = item.attributes
    return {
        id: numberValue(item.id),
        action_id: stringValue(a.action_id),
        enabled: Boolean(a.enabled),
        cron: nullableString(a.cron),
        timezone: stringValue(a.timezone) || 'Europe/Moscow',
        input: objectValue(a.input),
        options: objectValue(a.options),
        settings: objectValue(a.settings),
        last_run_at: nullableString(a.last_run_at),
        next_run_at: nullableString(a.next_run_at),
    }
}

export const actionScheduleRepository = {
    async list(): Promise<ScheduleListItem[]> {
        const payload = await getJson<{ items: ScheduleListItem[] }>(
            '/api/actions/schedules',
            'Не удалось загрузить расписания.',
        )
        return payload.items ?? []
    },

    async get(actionId: string): Promise<ActionSchedule | null> {
        const payload = await getJson<{ data: JsonApiItem | null }>(
            `/api/actions/${actionId}/schedule`,
            'Не удалось загрузить расписание.',
        )
        return payload.data ? normalize(payload.data) : null
    },

    async upsert(actionId: string, body: SchedulePayload): Promise<ActionSchedule> {
        const payload = await sendJson<{ data: JsonApiItem }>(`/api/actions/${actionId}/schedule`, {
            method: 'PUT',
            body,
            fallbackMessage: 'Не удалось сохранить расписание.',
        })
        return normalize(payload.data)
    },

    async remove(actionId: string): Promise<void> {
        await destroyJson(`/api/actions/${actionId}/schedule`, 'Не удалось удалить расписание.')
    },
}
