import {getJson} from '@/lib/http'
import type {ActionFeedResponse} from '@/modules/actions/types/feed'

export const actionFeedRepository = {
    async fetch(qs: URLSearchParams): Promise<ActionFeedResponse> {
        return getJson<ActionFeedResponse>(
            `/api/actions/feed?${qs.toString()}`,
            'Не удалось загрузить действия.',
        )
    },
}
