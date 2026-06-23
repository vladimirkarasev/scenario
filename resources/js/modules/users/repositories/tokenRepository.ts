import {destroyJson, getJson, sendJson} from '@/lib/http'
import type {ApiToken, ApiTokenCreated} from '@/modules/users/types/user'

export const tokenRepository = {
    async list(userId: string): Promise<ApiToken[]> {
        const raw = await getJson(`/api/users/${userId}/tokens`, 'Не удалось загрузить токены.') as { data: ApiToken[] }
        return raw.data
    },

    async create(userId: string, name: string, expiresAt: string | null): Promise<ApiTokenCreated> {
        const raw = await sendJson(`/api/users/${userId}/tokens`, {
            method: 'POST',
            body: {name, expires_at: expiresAt},
            fallbackMessage: 'Не удалось создать токен.',
        }) as { data: ApiTokenCreated }
        return raw.data
    },

    async revoke(userId: string, tokenId: number): Promise<void> {
        await destroyJson(`/api/users/${userId}/tokens/${tokenId}`, 'Не удалось отозвать токен.')
    },
}
