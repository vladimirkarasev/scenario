import {beforeEach, describe, expect, it, vi} from 'vitest'
import {sendJson} from '@/lib/http'
import {expressionRepository} from '@/modules/expression/repositories/expressionRepository'

vi.mock('@/lib/http', () => ({
    sendJson: vi.fn(),
}))

describe('expressionRepository', () => {
    beforeEach(() => vi.clearAllMocks())

    it('рендерит один шаблон', async () => {
        vi.mocked(sendJson).mockResolvedValue({data: {rendered: 'Привет, Иван'}})

        await expect(expressionRepository.render('Привет, {{ name }}', {name: 'Иван'}))
            .resolves.toEqual({rendered: 'Привет, Иван'})

        expect(sendJson).toHaveBeenCalledWith('/api/expression/render', {
            body: {template: 'Привет, {{ name }}', context: {name: 'Иван'}},
            fallbackMessage: 'Не удалось сформировать пример выражения.',
        })
    })

    it('передаёт один шаблон и контексты с id для batch', async () => {
        const template = '{{ region ?: city ?: category }}'
        const context = [
            {id: 1, data: {region: '', city: 'Москва'}},
            {id: 2, data: {category: 'СПБ'}},
        ]
        vi.mocked(sendJson).mockResolvedValue({data: {'1': 'Москва', '2': 'СПБ'}})

        await expect(expressionRepository.renderBatch(template, context)).resolves.toEqual({'1': 'Москва', '2': 'СПБ'})

        expect(sendJson).toHaveBeenCalledWith('/api/expression/render-batch', {
            body: {item: {template}, context},
            fallbackMessage: 'Не удалось сформировать примеры выражений.',
        })
    })
})
