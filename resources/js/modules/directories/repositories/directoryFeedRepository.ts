import {getJson} from '@/lib/http'
import type {DirectoryFeedResponse} from '@/modules/directories/types/feed'

export const directoryFeedRepository = {
    async fetch(qs: URLSearchParams): Promise<DirectoryFeedResponse> {
        return getJson<DirectoryFeedResponse>(
            `/api/directories/feed?${qs.toString()}`,
            'Не удалось загрузить справочники.',
        )
    },
}
