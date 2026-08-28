import {getJson} from '@/lib/http'
import type {ScenarioType} from '@/modules/scenario/types/scenario'
import type {ScenarioSystemVariableCatalog} from '@/modules/scenario/types/scenario-system-variable'

interface ScenarioSystemVariableResponse {
    data: ScenarioSystemVariableCatalog
}

export const scenarioSystemVariableRepository = {
    async get(type: ScenarioType): Promise<ScenarioSystemVariableCatalog> {
        const query = new URLSearchParams({type})
        const response = await getJson<ScenarioSystemVariableResponse>(
            `/api/scenarios/system-variables?${query.toString()}`,
            'Не удалось загрузить системные переменные.',
        )

        return response.data
    },
}
