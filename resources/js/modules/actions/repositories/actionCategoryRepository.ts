import {destroyJson, getJson, sendJson} from '@/lib/http'
import type {ActionCategory} from '@/modules/actions/types/action'

interface JsonApiItem {
    id: string
    attributes: {
        parent_id: string | null
        name: string
        is_active: boolean
        is_system?: boolean
        created_at?: string | null
        updated_at?: string | null
    }
    relationships?: {
        children?: { meta?: { count?: number } }
    }
}

function normalize(item: JsonApiItem): ActionCategory {
    return {
        id: item.id,
        parent_id: item.attributes?.parent_id ?? null,
        name: item.attributes?.name ?? '',
        is_active: item.attributes?.is_active ?? true,
        is_system: item.attributes?.is_system ?? false,
        children_count: item.relationships?.children?.meta?.count ?? 0,
        created_at: item.attributes?.created_at ?? null,
        updated_at: item.attributes?.updated_at ?? null,
    }
}

export interface ActionCategoryPayload {
    name: string
    parent_id: string | null
    is_active: boolean
}

export const actionCategoryRepository = {
    async all(): Promise<ActionCategory[]> {
        const raw = await getJson<{ data: JsonApiItem[] }>(
            '/api/actions/categories',
            'Не удалось загрузить разделы.',
        )
        return (raw.data ?? []).map(normalize)
    },

    async byParent(parentId: string | null): Promise<ActionCategory[]> {
        const qs = new URLSearchParams()
        qs.set('filter[parent_id]', parentId ?? 'null')
        const raw = await getJson<{ data: JsonApiItem[] }>(
            `/api/actions/categories?${qs}`,
            'Не удалось загрузить разделы.',
        )
        return (raw.data ?? []).map(normalize)
    },

    async create(payload: ActionCategoryPayload): Promise<ActionCategory> {
        const raw = await sendJson<{ data: JsonApiItem }>('/api/actions/categories', {
            body: payload,
            fallbackMessage: 'Не удалось создать раздел.',
        })
        return normalize(raw.data)
    },

    async update(id: string, payload: ActionCategoryPayload): Promise<ActionCategory> {
        const raw = await sendJson<{ data: JsonApiItem }>(`/api/actions/categories/${id}`, {
            method: 'PUT',
            body: payload,
            fallbackMessage: 'Не удалось обновить раздел.',
        })
        return normalize(raw.data)
    },

    async remove(id: string): Promise<void> {
        await destroyJson(`/api/actions/categories/${id}`, 'Не удалось удалить раздел.')
    },
}
