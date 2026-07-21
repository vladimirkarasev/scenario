import {destroyJson, getJson, sendJson} from '@/lib/http'
import type {User, UsersPage, UserPayload} from '@/modules/users/types/user'

interface RawRelRef {
    type: string
    id: string
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

interface RawIncluded {
    type: string
    id: string
    attributes: Record<string, unknown>
}

const INCLUDE_PARAMS: Record<string, string> = {
    include: 'roles,groups',
    'fields[groups]': 'name,slug',
    'fields[roles]': 'name,title',
}

function withIncludes(qs: URLSearchParams): URLSearchParams {
    for (const [key, value] of Object.entries(INCLUDE_PARAMS)) qs.set(key, value)
    return qs
}

function indexIncluded(included: RawIncluded[]): Map<string, RawIncluded> {
    const map = new Map<string, RawIncluded>()
    for (const item of included) map.set(`${item.type}:${item.id}`, item)
    return map
}

function str(value: unknown): string {
    return typeof value === 'string' ? value : ''
}

function normalizeUser(item: RawUser, included: Map<string, RawIncluded>): User {
    const attrsOf = (ref: RawRelRef): Record<string, unknown> =>
        included.get(`${ref.type}:${ref.id}`)?.attributes ?? {}

    return {
        id: item.id,
        name: item.attributes.name,
        fio: item.attributes.fio,
        email: item.attributes.email,
        login: item.attributes.login,
        external_id: item.attributes.external_id,
        created_at: item.attributes.created_at,
        roles: (item.relationships?.roles?.data ?? []).map((ref) => {
            const a = attrsOf(ref)
            return {id: ref.id, name: str(a.name), title: typeof a.title === 'string' ? a.title : null}
        }),
        groups: (item.relationships?.groups?.data ?? []).map((ref) => {
            const a = attrsOf(ref)
            return {id: ref.id, name: str(a.name), slug: str(a.slug)}
        }),
    }
}

export const userRepository = {
    async list(qs: URLSearchParams): Promise<UsersPage> {
        const raw = await getJson(`/api/users?${withIncludes(qs)}`, 'Не удалось загрузить пользователей.') as {
            data: RawUser[]
            included?: RawIncluded[]
            meta: UsersPage['meta']
        }
        const included = indexIncluded(raw.included ?? [])
        return {data: raw.data.map(u => normalizeUser(u, included)), meta: raw.meta}
    },

    async find(id: string): Promise<User> {
        const qs = withIncludes(new URLSearchParams())
        const raw = await getJson(`/api/users/${id}?${qs}`, 'Не удалось загрузить пользователя.') as {
            data: RawUser
            included?: RawIncluded[]
        }
        return normalizeUser(raw.data, indexIncluded(raw.included ?? []))
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
