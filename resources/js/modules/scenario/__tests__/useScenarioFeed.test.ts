import {ref} from 'vue'
import {describe, expect, it, vi} from 'vitest'
import {useScenarioFeed} from '@/modules/scenario/composables/useScenarioFeed'
import {scenarioFeedRepository} from '@/modules/scenario/repositories/scenarioFeedRepository'

vi.mock('@/modules/scenario/repositories/scenarioFeedRepository', () => ({
    scenarioFeedRepository: {
        fetch: vi.fn().mockResolvedValue({
            data: [],
            meta: {
                current_page: 1,
                last_page: 1,
                per_page: 20,
                total: 0,
                from: null,
                to: null,
                folders_total: 0,
                items_total: 0,
                counts_by_status: {all: 0, active: 0, draft: 0, archived: 0},
            },
        }),
    },
}))

describe('useScenarioFeed', () => {
    it('не передаёт parent_id для общего списка', async () => {
        useScenarioFeed(ref('all'), {syncUrl: false})

        await vi.waitFor(() => expect(scenarioFeedRepository.fetch).toHaveBeenCalledOnce())

        const query = vi.mocked(scenarioFeedRepository.fetch).mock.calls[0][0]
        expect(query.has('filter[parent_id]')).toBe(false)
    })
})
