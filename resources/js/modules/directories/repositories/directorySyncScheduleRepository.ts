import {destroyJson, getJson, sendJson} from '@/lib/http'

export interface DirectorySyncSchedule {
    enabled: boolean
    cron: string | null
    timezone: string
    last_run_at: string | null
    next_run_at: string | null
}

export interface DirectorySyncSchedulePayload {
    enabled: boolean
    cron: string | null
    timezone?: string | null
}

export const directorySyncScheduleRepository = {
    async get(directoryId: string): Promise<DirectorySyncSchedule | null> {
        const res = await getJson(
            `/api/actions/directories/${directoryId}/sync-schedule`,
            'Не удалось загрузить расписание синхронизации.',
        ) as { data: DirectorySyncSchedule | null }
        return res.data
    },

    async save(directoryId: string, payload: DirectorySyncSchedulePayload): Promise<DirectorySyncSchedule | null> {
        const res = await sendJson(`/api/actions/directories/${directoryId}/sync-schedule`, {
            method: 'PUT',
            body: payload,
            fallbackMessage: 'Не удалось сохранить расписание синхронизации.',
        }) as { data: DirectorySyncSchedule | null }
        return res.data
    },

    async remove(directoryId: string): Promise<void> {
        await destroyJson(`/api/actions/directories/${directoryId}/sync-schedule`, 'Не удалось удалить расписание синхронизации.')
    },
}
