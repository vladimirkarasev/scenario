import {destroyJson, getJson, sendJson} from '@/lib/http'

export interface ScenarioVersion {
    id: string
    scenario_id: string
    name: string | null
    status: string
    schema_json: Record<string, unknown>
    created_at: string | null
    updated_at: string | null
    revisions: { id: string; created_at: string | null }[]
}

export interface ScenarioVersionPayload {
    name?: string | null
    status?: string
    schema_json: Record<string, unknown>
}

interface RawVersionAttributes {
    scenario_id: string
    name: string | null
    status: string
    schema_json?: Record<string, unknown>
    created_at: string | null
    updated_at: string | null
    revisions?: { id: string; created_at: string | null }[]
}

interface RawVersion {
    id: string
    attributes: RawVersionAttributes
}

interface RawVersionFlat {
    id: string
    name: string | null
    status: string
    schema_json?: Record<string, unknown>
    created_at: string | null
    updated_at: string | null
    revisions?: { id: string; created_at: string | null }[]
}

function normalizeVersion(raw: RawVersion): ScenarioVersion {
    const a = raw.attributes
    return {
        id: raw.id,
        scenario_id: a.scenario_id,
        name: a.name,
        status: a.status,
        schema_json: a.schema_json ?? {},
        created_at: a.created_at,
        updated_at: a.updated_at,
        revisions: a.revisions ?? [],
    }
}

function normalizeVersionFlat(raw: RawVersionFlat, scenarioId: string): ScenarioVersion {
    return {
        id: raw.id,
        scenario_id: scenarioId,
        name: raw.name,
        status: raw.status,
        schema_json: raw.schema_json ?? {},
        created_at: raw.created_at,
        updated_at: raw.updated_at,
        revisions: raw.revisions ?? [],
    }
}

export const scenarioVersionRepository = {
    async list(scenarioId: string): Promise<ScenarioVersion[]> {
        const raw = await getJson(`/api/scenarios/${scenarioId}/versions`, 'Не удалось загрузить версии.') as {
            data: RawVersion[]
        }
        return raw.data.map(normalizeVersion)
    },

    async find(scenarioId: string, versionId: string): Promise<ScenarioVersion> {
        const raw = await getJson(`/api/scenarios/${scenarioId}/versions/${versionId}`, 'Не удалось загрузить версию.') as {
            data: RawVersion
        }
        return normalizeVersion(raw.data)
    },

    async create(scenarioId: string, payload: ScenarioVersionPayload): Promise<ScenarioVersion> {
        const raw = await sendJson(`/api/scenarios/${scenarioId}/versions`, {
            method: 'POST',
            body: payload,
            fallbackMessage: 'Не удалось создать версию.'
        }) as { data: RawVersionFlat }
        return normalizeVersionFlat(raw.data, scenarioId)
    },

    async update(scenarioId: string, versionId: string, payload: ScenarioVersionPayload): Promise<ScenarioVersion> {
        const raw = await sendJson(`/api/scenarios/${scenarioId}/versions/${versionId}`, {
            method: 'PUT',
            body: payload,
            fallbackMessage: 'Не удалось сохранить версию.'
        }) as { data: RawVersionFlat }
        return normalizeVersionFlat(raw.data, scenarioId)
    },

    async duplicate(scenarioId: string, versionId: string): Promise<ScenarioVersion> {
        const raw = await sendJson(`/api/scenarios/${scenarioId}/versions/${versionId}/duplicate`, {
            method: 'POST',
            body: {},
            fallbackMessage: 'Не удалось дублировать версию.'
        }) as { data: RawVersionFlat }
        return normalizeVersionFlat(raw.data, scenarioId)
    },

    async remove(scenarioId: string, versionId: string): Promise<void> {
        await destroyJson(`/api/scenarios/${scenarioId}/versions/${versionId}`, 'Не удалось удалить версию.')
    },
}
