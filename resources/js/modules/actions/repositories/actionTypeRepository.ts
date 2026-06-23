import {getJson} from '@/lib/http'
import type {ActionTypeMeta} from '@/modules/actions/types/action'

export const actionTypeRepository = {
    async list(): Promise<ActionTypeMeta[]> {
        const payload = await getJson<{ items: ActionTypeMeta[] }>(
            '/api/actions/types',
            'Не удалось загрузить типы actions.',
        )
        return payload.items ?? []
    },
}
