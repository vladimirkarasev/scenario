import {getJson} from '@/lib/http'
import type {ScenarioFeedResponse} from '@/modules/scenario/types/scenario-feed'

export const scenarioFeedRepository = {
    async fetch(qs: URLSearchParams): Promise<ScenarioFeedResponse> {
        return getJson<ScenarioFeedResponse>(
            `/api/scenarios/feed?${qs.toString()}`,
            'Не удалось загрузить сценарии.',
        )
    },
}
