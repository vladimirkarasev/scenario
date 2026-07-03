import {getJson} from '@/lib/http'
import type {ActionRun} from '@/modules/actions/types/action'

export const actionRunRepository = {
    async list(qs: URLSearchParams): Promise<ActionRun[]> {
        const payload = await getJson<{ items: ActionRun[] }>(
            `/api/actions/runs?${qs}`,
            'Не удалось загрузить историю запусков.',
        )
        return payload.items ?? []
    },
}
