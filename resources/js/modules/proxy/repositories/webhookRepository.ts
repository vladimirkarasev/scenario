import {getJson, sendJson} from '@/lib/http'
import type {HandlerOption, WebhookEndpoint, WebhookField, WebhookPayload} from '@/modules/proxy/types/webhook'

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

    async create(payload: WebhookPayload): Promise<WebhookEndpoint> {
        const raw = await sendJson('/api/proxy/endpoints', {
            method: 'POST',
            body: payload,
            fallbackMessage: 'Не удалось создать интеграцию.',
        }) as { item: WebhookEndpoint }
        return raw.item
    },

    async update(id: number, payload: WebhookPayload): Promise<WebhookEndpoint> {
        const raw = await sendJson(`/api/proxy/endpoints/${id}`, {
            method: 'PUT',
            body: payload,
            fallbackMessage: 'Не удалось сохранить интеграцию.',
        }) as { item: WebhookEndpoint }
        return raw.item
    },

    async destroy(id: number): Promise<void> {
        await sendJson(`/api/proxy/endpoints/${id}`, {
            method: 'DELETE',
            body: {},
            fallbackMessage: 'Не удалось удалить интеграцию.',
        })
    },

    async handlers(): Promise<HandlerOption[]> {
        const raw = await getJson('/api/proxy/handlers', 'Не удалось загрузить обработчики.') as {
            items: HandlerOption[]
        }
        return raw.items
    },

    async fields(webhookUuid: string): Promise<WebhookField[]> {
        const raw = await getJson(`/api/proxies/${webhookUuid}/fields`, '') as { items: WebhookField[] }
        return raw.items
    },
}
