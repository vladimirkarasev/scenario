import { destroyJson, getJson, sendJson } from '@/lib/http'

export interface CategoryRef {
    id: string
    parent_id: string | null
    name: string
    is_active: boolean
    children_count: number
    group_ids?: string[]
    created_at?: string | null
    updated_at?: string | null
}

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

export interface CategoryOption {
    id: string
    name: string
    parent_id: string | null
}

export const categoryRepository = {
    // Лёгкая загрузка дерева для выбора: только id + name + parent_id (JSON:API sparse fieldset).
    async options(): Promise<CategoryOption[]> {
        const qs = new URLSearchParams({ 'fields[category]': 'name,parent_id' })
        const response = await getJson<Record<string, unknown>>(
            `/api/directories/categories?${qs}`,
            'Не удалось загрузить категории.',
        )
        return ((response.data ?? []) as JsonApiItem[]).map(item => ({
            id: item.id,
            name: item.attributes?.name ?? '',
            parent_id: item.attributes?.parent_id ?? null,
        }))
    },

    async list(parentId?: string | null): Promise<{ items: CategoryRef[] }> {
        const qs = new URLSearchParams()
        if (parentId !== undefined) {
            qs.set('filter[parent_id]', parentId === null ? 'null' : parentId)
        }
        const url = `/api/directories/categories${qs.toString() ? `?${qs}` : ''}`
        const response = await getJson<Record<string, unknown>>(url, 'Не удалось загрузить категории.')
        const items: CategoryRef[] = ((response.data ?? []) as JsonApiItem[]).map(normalize)
        return { items }
    },

    async create(payload: { name: string; parent_id: string | null; is_active: boolean }): Promise<CategoryRef> {
        const response = await sendJson<Record<string, unknown>>('/api/directories/categories', {
            body: payload,
            fallbackMessage: 'Не удалось создать раздел.',
        })
        return normalize(response.data as JsonApiItem)
    },

    async update(id: string, payload: { name: string; parent_id: string | null; is_active: boolean }): Promise<CategoryRef> {
        const response = await sendJson<Record<string, unknown>>(`/api/directories/categories/${id}`, {
            method: 'PUT',
            body: payload,
            fallbackMessage: 'Не удалось обновить раздел.',
        })
        return normalize(response.data as JsonApiItem)
    },

    async remove(id: string): Promise<void> {
        await destroyJson(`/api/directories/categories/${id}`, 'Не удалось удалить раздел.')
    },
}
