import {getJson, sendJson} from '@/lib/http'
import type {PaginationMeta} from '@/types/pagination'
import type {HandlerOption, WebhookEndpoint, WebhookField, WebhookPayload} from '@/modules/proxy/types/webhook'

interface RawHandler {
    id: string
    attributes: { label: string, group: string, credential_type: string | null }
}

interface JsonApiResource {
    id: string
    attributes: Record<string, unknown>
}

interface RawField {
    attributes: WebhookField
}

/** Разворачивает JSON:API-ресурс ({id, attributes}) в плоский WebhookEndpoint. */
function flatten(resource: JsonApiResource): WebhookEndpoint {
    return {id: Number(resource.id), ...resource.attributes} as unknown as WebhookEndpoint
}

export const webhookRepository = {
    async list(qs: URLSearchParams = new URLSearchParams()): Promise<WebhookEndpoint[]> {
        const suffix = qs.toString() ? `?${qs}` : ''
        const raw = await getJson(`/api/proxy/endpoints${suffix}`, 'Не удалось загрузить эндпоинты.') as {
            data: JsonApiResource[]
        }
        return raw.data.map(flatten)
    },

    async find(id: number): Promise<WebhookEndpoint> {
        const raw = await getJson(`/api/proxy/endpoints/${id}`, 'Не удалось загрузить эндпоинт.') as {
            data: JsonApiResource
        }
        return flatten(raw.data)
    },

    async create(payload: WebhookPayload): Promise<WebhookEndpoint> {
        const raw = await sendJson('/api/proxy/endpoints', {
            method: 'POST',
            body: payload,
            fallbackMessage: 'Не удалось создать интеграцию.',
        }) as { data: JsonApiResource }
        return flatten(raw.data)
    },

    async update(id: number, payload: WebhookPayload): Promise<WebhookEndpoint> {
        const raw = await sendJson(`/api/proxy/endpoints/${id}`, {
            method: 'PUT',
            body: payload,
            fallbackMessage: 'Не удалось сохранить интеграцию.',
        }) as { data: JsonApiResource }
        return flatten(raw.data)
    },

    async destroy(id: number): Promise<void> {
        await sendJson(`/api/proxy/endpoints/${id}`, {
            method: 'DELETE',
            body: {},
            fallbackMessage: 'Не удалось удалить интеграцию.',
        })
    },

    async handlers(qs: URLSearchParams): Promise<{ items: HandlerOption[], meta: PaginationMeta }> {
        const raw = await getJson(`/api/proxy/handlers?${qs}`, 'Не удалось загрузить обработчики.') as {
            data: RawHandler[]
            meta: PaginationMeta
        }
        return {
            items: raw.data.map(h => ({
                class: h.id,
                label: h.attributes.label,
                group: h.attributes.group,
                credential_type: h.attributes.credential_type,
            })),
            meta: raw.meta,
        }
    },

    async fields(webhookUuid: string): Promise<WebhookField[]> {
        const raw = await getJson(
            `/api/proxies/${webhookUuid}/fields`,
            'Не удалось загрузить поля интеграции.',
        ) as { data: RawField[] }
        return raw.data.map(item => item.attributes)
    },
}
