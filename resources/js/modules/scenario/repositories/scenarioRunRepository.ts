import {getJson, sendJson} from '@/lib/http'
import type {ScenarioRunPayload} from '@/modules/scenario/lib/scenario-player-types'
import type {ScenarioActor, ScenarioRunsPage} from '@/modules/scenario/types/scenario'

function extractRun(raw: unknown): ScenarioRunPayload {
    return (raw as { run: ScenarioRunPayload }).run
}

export const scenarioRunRepository = {
    async list(qs: URLSearchParams): Promise<ScenarioRunsPage> {
        return getJson(`/api/scenarios/runner?${qs}`, 'Не удалось загрузить запуски.') as Promise<ScenarioRunsPage>
    },

    async users(search: string): Promise<ScenarioActor[]> {
        const qs = new URLSearchParams()
        if (search) qs.set('filter[search]', search)
        const raw = await getJson(`/api/scenarios/runner/users?${qs}`, 'Не удалось загрузить пользователей.') as {
            users: ScenarioActor[]
        }
        return raw.users
    },

    async usersByIds(ids: (string | number)[]): Promise<ScenarioActor[]> {
        if (!ids.length) return []
        const qs = new URLSearchParams()
        ids.forEach((id) => qs.append('filter[ids][]', String(id)))
        const raw = await getJson(`/api/scenarios/runner/users?${qs}`, 'Не удалось загрузить пользователей.') as {
            users: ScenarioActor[]
        }
        return raw.users
    },

    async create(payload: {
        scenario_id?: string | null
        scenario_version_id?: string | null
        context?: Record<string, unknown>
        user_data?: Record<string, unknown>
        skip_input_validation?: boolean
    }): Promise<ScenarioRunPayload> {
        const raw = await sendJson('/api/scenarios/runner', {
            method: 'POST',
            body: payload,
            fallbackMessage: 'Не удалось создать запуск.'
        })
        return extractRun(raw)
    },

    async find(runId: string): Promise<ScenarioRunPayload> {
        const raw = await getJson(`/api/scenarios/runner/${runId}`, 'Не удалось загрузить запуск.')
        return extractRun(raw)
    },

    async continue(runId: string, input: Record<string, unknown>, selectedTargetNodeId?: string | null): Promise<ScenarioRunPayload> {
        const raw = await sendJson(`/api/scenarios/runner/${runId}/continue`, {
            method: 'POST',
            body: {input, selected_target_node_id: selectedTargetNodeId ?? null},
            fallbackMessage: 'Не удалось продолжить запуск.',
        })
        return extractRun(raw)
    },

    async jump(runId: string, nodeId: string): Promise<ScenarioRunPayload> {
        const raw = await sendJson(`/api/scenarios/runner/${runId}/jump`, {
            method: 'POST',
            body: {node_id: nodeId},
            fallbackMessage: 'Не удалось перейти к узлу.',
        })
        return extractRun(raw)
    },

    async retryAction(runId: string): Promise<ScenarioRunPayload> {
        const raw = await sendJson(`/api/scenarios/runner/${runId}/retry-action`, {
            method: 'POST',
            body: {},
            fallbackMessage: 'Не удалось повторить действие.',
        })
        return extractRun(raw)
    },
}
