import {destroyJson, getJson, sendJson} from '@/lib/http'
import type {
    ScenarioVersion,
    ScenarioVersionHistoryPage,
    ScenarioVersionPayload,
    ScenarioVersionSettingsPayload,
    ScenarioVersionStatus,
} from '@/modules/scenario/types/scenario-version'

interface RawVersionAttributes {
    scenario_id: string
    name: string | null
    status: ScenarioVersionStatus
    schema_json?: Record<string, unknown>
    created_at: string | null
    updated_at: string | null
    revisions?: ScenarioVersion['revisions']
}

interface RawVersion {
    id: string
    attributes: RawVersionAttributes
}

interface RawVersionFlat extends Omit<RawVersionAttributes, 'scenario_id'> {
    id: string
    scenario_id?: string
}

function normalizeVersion(raw: RawVersion): ScenarioVersion {
    return normalizeVersionFlat({id: raw.id, ...raw.attributes}, raw.attributes.scenario_id)
}

function normalizeVersionFlat(raw: RawVersionFlat, scenarioId: string): ScenarioVersion {
    return {
        id: raw.id,
        scenario_id: raw.scenario_id ?? scenarioId,
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

    async editor(scenarioId: string, versionId: string): Promise<ScenarioVersion> {
        const raw = await getJson(
            `/api/scenarios/${scenarioId}/versions/${versionId}/editor`,
            'Не удалось загрузить редактор версии.',
        ) as {data: RawVersionFlat}
        return normalizeVersionFlat(raw.data, scenarioId)
    },

    async settings(scenarioId: string, versionId: string): Promise<ScenarioVersion> {
        const raw = await getJson(
            `/api/scenarios/${scenarioId}/versions/${versionId}/settings`,
            'Не удалось загрузить настройки версии.',
        ) as {data: RawVersionFlat}
        return normalizeVersionFlat(raw.data, scenarioId)
    },

    async history(scenarioId: string, versionId: string, page: number, perPage = 20): Promise<ScenarioVersionHistoryPage> {
        const query = new URLSearchParams({
            'page[number]': String(page),
            'page[size]': String(perPage),
        })
        return await getJson(
            `/api/scenarios/${scenarioId}/versions/${versionId}/revisions?${query}`,
            'Не удалось загрузить историю версии.',
        ) as ScenarioVersionHistoryPage
    },

    async create(scenarioId: string, payload: ScenarioVersionPayload): Promise<ScenarioVersion> {
        const raw = await sendJson(`/api/scenarios/${scenarioId}/versions`, {
            method: 'POST', body: payload, fallbackMessage: 'Не удалось создать версию.',
        }) as {data: RawVersionFlat}
        return normalizeVersionFlat(raw.data, scenarioId)
    },

    async update(scenarioId: string, versionId: string, payload: ScenarioVersionPayload): Promise<ScenarioVersion> {
        const raw = await sendJson(`/api/scenarios/${scenarioId}/versions/${versionId}`, {
            method: 'PUT', body: payload, fallbackMessage: 'Не удалось сохранить версию.',
        }) as {data: RawVersionFlat}
        return normalizeVersionFlat(raw.data, scenarioId)
    },

    async updateSettings(
        scenarioId: string,
        versionId: string,
        payload: ScenarioVersionSettingsPayload,
    ): Promise<ScenarioVersion> {
        const raw = await sendJson(`/api/scenarios/${scenarioId}/versions/${versionId}/settings`, {
            method: 'PUT', body: payload, fallbackMessage: 'Не удалось сохранить настройки версии.',
        }) as {data: RawVersionFlat}
        return normalizeVersionFlat(raw.data, scenarioId)
    },

    async duplicate(scenarioId: string, versionId: string): Promise<ScenarioVersion> {
        const raw = await sendJson(`/api/scenarios/${scenarioId}/versions/${versionId}/duplicate`, {
            method: 'POST', body: {}, fallbackMessage: 'Не удалось дублировать версию.',
        }) as {data: RawVersionFlat}
        return normalizeVersionFlat(raw.data, scenarioId)
    },

    async remove(scenarioId: string, versionId: string): Promise<void> {
        await destroyJson(`/api/scenarios/${scenarioId}/versions/${versionId}`, 'Не удалось удалить версию.')
    },
}
