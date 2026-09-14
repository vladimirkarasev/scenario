import {destroyJson, getJson, sendJson} from '@/lib/http'
import type {
    Scenario,
    ScenariosPage,
    ScenarioPayload,
    ScenarioCategory,
    ScenarioVersionRef,
    ScenarioActor,
    ScenarioStatus,
    ScenarioType,
    GroupRef
} from '@/modules/scenario/types/scenario'
import type {FeedFolder, FeedScenario, ScenarioFeedResult} from '@/modules/scenario/types/scenario-feed'

interface RawScenarioAttributes {
    name: string
    description: string | null
    is_active: boolean
    status: ScenarioStatus
    type: ScenarioType
    alias: string | null
    tags: string[] | null
    active_version_id: string | null
    created_at: string | null
    updated_at: string | null
    created_by: ScenarioActor | null
    updated_by: ScenarioActor | null
    versions: ScenarioVersionRef[]
    groups?: GroupRef[]
}

interface RawScenario {
    id: string
    attributes: RawScenarioAttributes
    relationships?: {
        categories?: {
            data?: Array<{ id: string; type: string }>
        }
    }
}

interface RawIncludedItem {
    id: string
    type: string
    attributes: Record<string, unknown>
    relationships?: Record<string, unknown>
}

function buildCategoryMap(included: RawIncludedItem[]): Map<string, ScenarioCategory> {
    const map = new Map<string, ScenarioCategory>()
    for (const item of included) {
        if (item.type !== 'category') continue
        const a = item.attributes
        const childRel = item.relationships?.['children'] as { meta?: { count?: number } } | undefined
        map.set(item.id, {
            id: item.id,
            parent_id: typeof a['parent_id'] === 'string' ? a['parent_id'] : null,
            name: typeof a['name'] === 'string' ? a['name'] : '',
            is_active: typeof a['is_active'] === 'boolean' ? a['is_active'] : false,
            children_count: typeof childRel?.meta?.count === 'number' ? childRel.meta.count : 0,
            created_at: typeof a['created_at'] === 'string' ? a['created_at'] : null,
            updated_at: typeof a['updated_at'] === 'string' ? a['updated_at'] : null,
        })
    }
    return map
}

function normalizeScenario(raw: RawScenario, catMap: Map<string, ScenarioCategory>): Scenario {
    const a = raw.attributes
    const catIds = raw.relationships?.categories?.data?.map(r => r.id) ?? []
    const categories = catIds.map(id => catMap.get(id)).filter((c): c is ScenarioCategory => c !== undefined)
    return {
        id: raw.id,
        name: a.name,
        description: a.description,
        is_active: a.is_active,
        status: a.status,
        type: a.type ?? 'colls',
        alias: a.alias,
        tags: a.tags ?? [],
        active_version_id: a.active_version_id,
        created_at: a.created_at,
        updated_at: a.updated_at,
        created_by: a.created_by,
        updated_by: a.updated_by,
        categories,
        groups: a.groups ?? [],
        versions: a.versions ?? [],
    }
}

interface RawCategory {
    id: string
    attributes: {
        parent_id: string | null
        name: string
        is_active: boolean
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

function normalizeCategory(r: RawCategory): ScenarioCategory {
    return {
        id: r.id,
        parent_id: r.attributes.parent_id,
        name: r.attributes.name,
        is_active: r.attributes.is_active,
        children_count: r.relationships?.children?.meta?.count ?? 0,
        created_at: r.attributes.created_at ?? null,
        updated_at: r.attributes.updated_at ?? null,
    }
}

interface FeedRow {
    type?: string

    [key: string]: unknown
}

function parseFeedRows(rows: FeedRow[]): ScenarioFeedResult {
    const folders: FeedFolder[] = rows.filter(r => r.type === 'folder').map(r => ({
        id: String(r.id),
        name: typeof r.name === 'string' ? r.name : '',
        parent_id: typeof r.parent_id === 'string' ? r.parent_id : null,
        parent_path: typeof r.parent_path === 'string' ? r.parent_path : '',
        path_ids: Array.isArray(r.path_ids) ? r.path_ids.map(String) : [],
        children_count: typeof r.children_count === 'number' ? r.children_count : 0,
    }))
    const scenarios: FeedScenario[] = rows.filter(r => r.type === 'scenario').map(r => ({
        id: String(r.id),
        name: typeof r.name === 'string' ? r.name : '',
        status: (typeof r.status === 'string' ? r.status : 'draft') as ScenarioStatus,
        scenario_type: (typeof r.scenario_type === 'string' ? r.scenario_type : 'colls') as ScenarioType,
        folder_id: typeof r.folder_id === 'string' ? r.folder_id : null,
        folder_path: typeof r.folder_path === 'string' ? r.folder_path : '',
        active_version_id: typeof r.active_version_id === 'string' ? r.active_version_id : null,
    }))
    return {folders, scenarios}
}

export const scenarioRepository = {
    async workspaceCategoryId(): Promise<string | null> {
        const qs = new URLSearchParams()
        qs.set('filter[is_workspace]', 'true')

        const raw = await getJson<{ data?: Array<{ id: string }> }>(
            `/api/scenarios/categories?${qs}`,
            'Не удалось определить рабочую папку.',
        )
        return raw.data?.[0]?.id ?? null
    },

    async categories(): Promise<ScenarioCategory[]> {
        const raw = await getJson<Record<string, unknown>>('/api/scenarios/categories', 'Не удалось загрузить категории.') as {
            data: RawCategory[]
        }
        return raw.data.map(normalizeCategory)
    },

    async categoriesByParent(parentId: string | null): Promise<ScenarioCategory[]> {
        const qs = new URLSearchParams()
        qs.set('filter[parent_id]', parentId ?? 'null')
        const raw = await getJson<Record<string, unknown>>(`/api/scenarios/categories?${qs}`, 'Не удалось загрузить категории.') as {
            data: RawCategory[]
        }
        return raw.data.map(normalizeCategory)
    },

    async feed(params: { parentId?: string | null; rootId?: string | null; search?: string | null; projectId?: string | null }): Promise<ScenarioFeedResult> {
        const qs = new URLSearchParams()
        qs.set('page[size]', '100')
        if (params.parentId !== undefined) qs.set('filter[parent_id]', params.parentId ?? 'null')
        if (params.rootId) qs.set('filter[root_id]', params.rootId)
        if (params.search) qs.set('filter[search]', params.search)
        if (params.projectId) qs.set('filter[project_id]', params.projectId)

        const raw = await getJson<Record<string, unknown>>(`/api/scenarios/feed?${qs}`, 'Не удалось загрузить.') as {
            data?: FeedRow[]
        }
        return parseFeedRows(Array.isArray(raw.data) ? raw.data : [])
    },

    async list(qs: URLSearchParams): Promise<ScenariosPage> {
        const raw = await getJson<Record<string, unknown>>(`/api/scenarios?${qs}`, 'Не удалось загрузить сценарии.') as {
            data: RawScenario[]
            meta: ScenariosPage['meta']
            included?: RawIncludedItem[]
        }
        const catMap = buildCategoryMap(raw.included ?? [])
        return {
            data: raw.data.map(s => normalizeScenario(s, catMap)),
            meta: raw.meta,
            includedCategories: [...catMap.values()],
        }
    },

    async find(id: string): Promise<Scenario> {
        const raw = await getJson<Record<string, unknown>>(`/api/scenarios/${id}`, 'Не удалось загрузить сценарий.') as {
            data: RawScenario
            included?: RawIncludedItem[]
        }
        const catMap = buildCategoryMap(raw.included ?? [])
        return normalizeScenario(raw.data, catMap)
    },

    async create(payload: ScenarioPayload): Promise<Scenario> {
        const raw = await sendJson<Record<string, unknown>>('/api/scenarios', {
            method: 'POST',
            body: payload,
            fallbackMessage: 'Не удалось создать сценарий.'
        })
        return raw.data as Scenario
    },

    async update(id: string, payload: ScenarioPayload): Promise<Scenario> {
        const raw = await sendJson<Record<string, unknown>>(`/api/scenarios/${id}`, {
            method: 'PUT',
            body: payload,
            fallbackMessage: 'Не удалось сохранить сценарий.'
        })
        return raw.data as Scenario
    },

    async duplicate(id: string): Promise<Scenario> {
        const raw = await sendJson<Record<string, unknown>>(`/api/scenarios/${id}/duplicate`, {
            method: 'POST',
            body: {},
            fallbackMessage: 'Не удалось дублировать сценарий.'
        })
        return raw.data as Scenario
    },

    async remove(id: string): Promise<void> {
        await destroyJson(`/api/scenarios/${id}`, 'Не удалось удалить сценарий.')
    },
}
