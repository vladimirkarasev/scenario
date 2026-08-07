import {destroyJson, getJson, sendJson} from '@/lib/http'
import type {PermissionOption, Role, RolePayload} from '@/modules/roles/types/role'

interface RawRole {
    id: string
    attributes: {
        name: string
        title: string | null
        description: string | null
        is_system: boolean
        guard_name: string
        permissions: string[]
        created_at: string | null
    }
    relationships?: { users?: { meta?: { count?: number } } }
}

function normalizeRole(r: RawRole): Role {
    return {
        id: Number(r.id),
        name: r.attributes.name,
        title: r.attributes.title ?? null,
        description: r.attributes.description ?? null,
        is_system: r.attributes.is_system ?? false,
        users_count: r.relationships?.users?.meta?.count ?? 0,
        permissions: r.attributes.permissions ?? [],
    }
}

export const roleRepository = {
    async list(qs: URLSearchParams): Promise<Role[]> {
        const raw = await getJson(`/api/roles?${qs}`, 'Не удалось загрузить роли.') as { data: RawRole[] }
        return raw.data.map(normalizeRole)
    },

    async listPermissions(): Promise<PermissionOption[]> {
        const raw = await getJson('/api/permissions', 'Не удалось загрузить разрешения.') as {
            data: PermissionOption[]
        }
        return raw.data
    },

    async create(payload: RolePayload): Promise<Role> {
        const raw = await sendJson('/api/roles', {
            method: 'POST',
            body: payload,
            fallbackMessage: 'Не удалось создать роль.'
        }) as { data: RawRole }
        return normalizeRole(raw.data)
    },

    async update(id: number, payload: RolePayload): Promise<Role> {
        const raw = await sendJson(`/api/roles/${id}`, {
            method: 'PUT',
            body: payload,
            fallbackMessage: 'Не удалось сохранить роль.'
        }) as { data: RawRole }
        return normalizeRole(raw.data)
    },

    async remove(id: number): Promise<void> {
        await destroyJson(`/api/roles/${id}`, 'Не удалось удалить роль.')
    },

    async saveAt(url: string, name: string, isUpdate: boolean): Promise<Pick<Role, 'id' | 'name'>> {
        const response = await sendJson<{item: Pick<Role, 'id' | 'name'>}>(url, {
            method: isUpdate ? 'PUT' : 'POST',
            body: {name},
            fallbackMessage: 'Не удалось сохранить роль.',
        })
        return response.item
    },

    async removeAt(url: string): Promise<void> {
        await destroyJson(url, 'Не удалось удалить роль.')
    },
}
