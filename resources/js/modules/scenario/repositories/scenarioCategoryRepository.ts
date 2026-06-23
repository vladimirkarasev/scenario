import {destroyJson, getJson, sendJson} from '@/lib/http'
import type {CategoryRef} from '@/modules/scenario/repositories/categoryRepository'

interface JsonApiItem {
    id: string
    attributes: {
        parent_id: string | null
        name: string
        is_active: boolean
        group_ids?: string[]
        created_at?: string | null
        updated_at?: string | null
    }
    relationships?: {
        children?: {
            meta?: {
                count?: number
            }
        }
    }
}

function normalize(item: JsonApiItem): CategoryRef {
    return {
        id: item.id,
        parent_id: item.attributes?.parent_id ?? null,
        name: item.attributes?.name ?? '',
        is_active: item.attributes?.is_active ?? true,
        children_count: item.relationships?.children?.meta?.count ?? 0,
        group_ids: item.attributes?.group_ids ?? [],
        created_at: item.attributes?.created_at ?? null,
        updated_at: item.attributes?.updated_at ?? null,
    }
}

export interface ScenarioCategoryPayload {
    name: string
    parent_id: string | null
    is_active: boolean
    group_ids?: string[]
    inherit_to_descendants?: boolean
}

export const scenarioCategoryRepository = {
    async list(parentId?: string | null): Promise<{ items: CategoryRef[] }> {
        const qs = new URLSearchParams()
        if (parentId !== undefined) {
            qs.set('filter[parent_id]', parentId === null ? 'null' : parentId)
        }
        const url = `/api/scenarios/categories${qs.toString() ? `?${qs}` : ''}`
        const response = await getJson<Record<string, unknown>>(url, 'Не удалось загрузить разделы.')
        const items: CategoryRef[] = ((response.data ?? []) as JsonApiItem[]).map(normalize)
        return {items}
    },

    async create(payload: ScenarioCategoryPayload): Promise<CategoryRef> {
        const response = await sendJson<Record<string, unknown>>('/api/scenarios/categories', {
            body: payload,
            fallbackMessage: 'Не удалось создать раздел.',
        })
        return normalize(response.data as JsonApiItem)
    },

    async update(id: string, payload: ScenarioCategoryPayload): Promise<CategoryRef> {
        const response = await sendJson<Record<string, unknown>>(`/api/scenarios/categories/${id}`, {
            method: 'PUT',
            body: payload,
            fallbackMessage: 'Не удалось обновить раздел.',
        })
        return normalize(response.data as JsonApiItem)
    },

    async remove(id: string): Promise<void> {
        await destroyJson(`/api/scenarios/categories/${id}`, 'Не удалось удалить раздел.')
    },
}
