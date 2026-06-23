import {destroyJson, getJson, sendJson} from '@/lib/http'
import type {User, UsersPage, UserPayload} from '@/modules/users/types/user'

interface RawRelRef {
    id: string;
    meta?: Record<string, string>
}

interface RawUser {
    id: string
    attributes: {
        name: string
        fio: string | null
        email: string
        login: string | null
        external_id: string | null
        created_at: string | null
    }
    relationships?: {
        roles?: { data: RawRelRef[] }
        groups?: { data: RawRelRef[] }
    }
}

function normalizeUser(item: RawUser): User {
    return {
        id: item.id,
        name: item.attributes.name,
        fio: item.attributes.fio,
        email: item.attributes.email,
        login: item.attributes.login,
        external_id: item.attributes.external_id,
        created_at: item.attributes.created_at,
        roles: (item.relationships?.roles?.data ?? []).map(r => ({
            id: r.id,
            name: r.meta?.name ?? '',
            title: r.meta?.title ?? null,
        })),
        groups: (item.relationships?.groups?.data ?? []).map(g => ({
            id: g.id,
            name: g.meta?.name ?? '',
            slug: g.meta?.slug ?? '',
        })),
    }
}

export const userRepository = {
    async list(qs: URLSearchParams): Promise<UsersPage> {
        const raw = await getJson(`/api/users?${qs}`, 'Не удалось загрузить пользователей.') as {
            data: RawUser[]
            meta: UsersPage['meta']
        }
        return {data: raw.data.map(normalizeUser), meta: raw.meta}
    },

    async find(id: string): Promise<User> {
        const raw = await getJson(`/api/users/${id}`, 'Не удалось загрузить пользователя.') as { data: RawUser }
        return normalizeUser(raw.data)
    },

    async create(payload: UserPayload): Promise<void> {
        await sendJson('/api/users', {
            method: 'POST',
            body: payload,
            fallbackMessage: 'Не удалось создать пользователя.'
        })
    },

    async update(id: string, payload: UserPayload): Promise<void> {
        await sendJson(`/api/users/${id}`, {
            method: 'PUT',
            body: payload,
            fallbackMessage: 'Не удалось сохранить пользователя.'
        })
    },

    async remove(id: string): Promise<void> {
        await destroyJson(`/api/users/${id}`, 'Не удалось удалить пользователя.')
    },
}
