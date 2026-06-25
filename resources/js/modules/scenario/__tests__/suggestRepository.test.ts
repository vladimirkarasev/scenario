import {beforeEach, describe, expect, it, vi} from 'vitest'
import {suggestRepository} from '@/modules/scenario/repositories/suggestRepository'
import {sendJson} from '@/lib/http'

vi.mock('@/lib/http', () => ({
    sendJson: vi.fn(),
}))

describe('suggestRepository', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it.each([
        [[{id: 1}, null, 'text', ['nested'], {id: 2}], [{id: 1}, {id: 2}]],
        [{items: [{value: 'one'}, 42, {value: 'two'}]}, [{value: 'one'}, {value: 'two'}]],
        [{items: 'invalid'}, []],
        [null, []],
    ])('нормализует ответ proxy %#', async (response, expected) => {
        vi.mocked(sendJson).mockResolvedValue(response)

        await expect(suggestRepository.suggest('proxy-uuid', 'иван')).resolves.toEqual(expected)
        expect(sendJson).toHaveBeenCalledWith('/api/proxies/proxy-uuid', {
            method: 'POST',
            body: {query: 'иван'},
            fallbackMessage: 'Не удалось загрузить подсказки.',
        })
    })
})
