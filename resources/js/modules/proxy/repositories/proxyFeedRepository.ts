import {getJson} from '@/lib/http'
import type {
    FeedEndpointRow,
    FeedFolderRow,
    ProxyFeedPage,
    ProxyFeedRow,
} from '@/modules/proxy/types/feed'

interface RawFeedResource {
    type: 'proxy-folders' | 'proxy-endpoints'
    id: string
    attributes: Record<string, unknown>
}

function normalize(resource: RawFeedResource): ProxyFeedRow {
    if (resource.type === 'proxy-folders') {
        return {
            type: 'folder',
            id: resource.id,
            ...resource.attributes,
        } as FeedFolderRow
    }

    return {
        type: 'endpoint',
        id: Number(resource.id),
        ...resource.attributes,
    } as FeedEndpointRow
}

export const proxyFeedRepository = {
    async fetch(qs: URLSearchParams): Promise<ProxyFeedPage> {
        const raw = await getJson<{
            data: RawFeedResource[]
            meta: ProxyFeedPage['meta']
        }>(
            `/api/proxy/feed?${qs.toString()}`,
            'Не удалось загрузить интеграции.',
        )

        return {data: raw.data.map(normalize), meta: raw.meta}
    },
}
