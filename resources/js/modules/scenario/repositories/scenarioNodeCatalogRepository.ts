import {getJson} from '@/lib/http'
import type {ScenarioType} from '@/modules/scenario/types/scenario'
import type {ScenarioNodeCatalog} from '@/modules/scenario/types/scenario-node-catalog'

interface ScenarioNodeCatalogResponse {
    data: ScenarioNodeCatalog
}

export const scenarioNodeCatalogRepository = {
    async get(type: ScenarioType): Promise<ScenarioNodeCatalog> {
        const query = new URLSearchParams({type})
        const response = await getJson<ScenarioNodeCatalogResponse>(
            `/api/scenarios/node-types?${query.toString()}`,
            'Не удалось загрузить доступные узлы.',
        )

        return response.data
    },
}
