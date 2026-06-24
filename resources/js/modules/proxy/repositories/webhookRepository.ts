import {getJson, sendJson} from '@/lib/http'
import type {WebhookEndpoint, WebhookField, WebhookPayload} from '@/modules/proxy/types/webhook'

export const webhookRepository = {
    async list(): Promise<WebhookEndpoint[]> {
        const raw = await getJson('/api/proxy/endpoints', 'Не удалось загрузить эндпоинты.') as {
            items: WebhookEndpoint[]
        }
        return raw.items
    },

    async listByType(type: string): Promise<WebhookEndpoint[]> {
        const qs = new URLSearchParams({'filter[type]': type})
        const raw = await getJson(`/api/proxy/endpoints?${qs}`, 'Не удалось загрузить эндпоинты.') as {
            items: WebhookEndpoint[]
        }
        return raw.items
    },

    async search(query: string): Promise<WebhookEndpoint[]> {
        const qs = new URLSearchParams({'filter[search]': query})
        const raw = await getJson(`/api/proxy/endpoints?${qs}`, 'Не удалось загрузить эндпоинты.') as {
            items: WebhookEndpoint[]
        }
        return raw.items
    },

    async find(id: number): Promise<WebhookEndpoint> {
        const raw = await getJson(`/api/proxy/endpoints/${id}`, 'Не удалось загрузить эндпоинт.') as {
            item: WebhookEndpoint
        }
        return raw.item
    },

    async update(id: number, payload: WebhookPayload): Promise<WebhookEndpoint> {
        const raw = await sendJson(`/api/proxy/endpoints/${id}`, {
            method: 'PUT',
            body: payload,
            fallbackMessage: 'Не удалось сохранить эндпоинт.',
        }) as { item: WebhookEndpoint }
        return raw.item
    },

    async fields(webhookUuid: string): Promise<WebhookField[]> {
        const raw = await getJson(`/api/proxies/${webhookUuid}/fields`, '') as { items: WebhookField[] }
        return raw.items
    },
}
