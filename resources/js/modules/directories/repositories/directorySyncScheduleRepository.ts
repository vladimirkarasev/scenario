import {destroyJson, getJson, sendJson} from '@/lib/http'
import type {DirectorySyncSchedule, DirectorySyncSchedulePayload} from '@/modules/directories/types/sync-schedule'

export const directorySyncScheduleRepository = {
    async get(directoryId: string): Promise<DirectorySyncSchedule | null> {
        const res = await getJson(
            `/api/directories/${directoryId}/sync-schedule`,
            'Не удалось загрузить расписание синхронизации.',
        ) as { data: DirectorySyncSchedule | null }
        return res.data
    },

    async save(directoryId: string, payload: DirectorySyncSchedulePayload): Promise<DirectorySyncSchedule | null> {
        const res = await sendJson(`/api/directories/${directoryId}/sync-schedule`, {
            method: 'PUT',
            body: payload,
            fallbackMessage: 'Не удалось сохранить расписание синхронизации.',
        }) as { data: DirectorySyncSchedule | null }
        return res.data
    },

    async remove(directoryId: string): Promise<void> {
        await destroyJson(`/api/directories/${directoryId}/sync-schedule`, 'Не удалось удалить расписание синхронизации.')
    },
}
