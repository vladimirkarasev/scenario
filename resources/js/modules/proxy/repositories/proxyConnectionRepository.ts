import {destroyJson, getJson, sendJson} from '@/lib/http'
import type {CredentialType, ProxyConnection, ProxyConnectionPayload} from '@/modules/proxy/types/connection'

interface JsonApiResource {
    id: string
    attributes: Record<string, unknown>
}

function flatten(resource: JsonApiResource): ProxyConnection {
    return {id: Number(resource.id), ...resource.attributes} as unknown as ProxyConnection
}

export const proxyConnectionRepository = {
    async list(credentialType?: string): Promise<ProxyConnection[]> {
        const qs = new URLSearchParams()
        if (credentialType) qs.set('filter[credential_type]', credentialType)
        const suffix = qs.toString() ? `?${qs}` : ''
        const raw = await getJson<{ data: JsonApiResource[] }>(
            `/api/proxy/connections${suffix}`,
            'Не удалось загрузить доступы.',
        )
        return raw.data.map(flatten)
    },

    async create(payload: ProxyConnectionPayload): Promise<ProxyConnection> {
        const raw = await sendJson<{ data: JsonApiResource }>('/api/proxy/connections', {
            method: 'POST',
            body: payload,
            fallbackMessage: 'Не удалось создать доступ.',
        })
        return flatten(raw.data)
    },

    async update(id: number, payload: ProxyConnectionPayload): Promise<ProxyConnection> {
        const raw = await sendJson<{ data: JsonApiResource }>(`/api/proxy/connections/${id}`, {
            method: 'PUT',
            body: payload,
            fallbackMessage: 'Не удалось сохранить доступ.',
        })
        return flatten(raw.data)
    },

    async destroy(id: number): Promise<void> {
        await destroyJson(`/api/proxy/connections/${id}`, 'Не удалось удалить доступ.')
    },

    async types(): Promise<CredentialType[]> {
        const raw = await getJson<{ data: CredentialType[] }>(
            '/api/proxy/credential-types',
            'Не удалось загрузить типы доступа.',
        )
        return raw.data ?? []
    },
}
