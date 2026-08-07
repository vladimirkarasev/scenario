import {describe, expect, it, vi} from 'vitest'
import {useExpressionLabelBatch} from '@/modules/expression/composables/useExpressionLabelBatch'
import type {ExpressionRepository} from '@/modules/expression/types/expression'

describe('useExpressionLabelBatch', () => {
    it('объединяет общий context с данными каждой строки', async () => {
        const renderBatch = vi.fn().mockResolvedValue({'1': 'Москва', '2': 'СПБ'})
        const repository: ExpressionRepository = {render: vi.fn(), renderBatch}
        const labels = useExpressionLabelBatch(repository)

        await labels.load('{{ region ?: city ?: category }}', [
            {id: 1, data: {region: '', city: 'Москва'}},
            {id: 2, data: {category: 'СПБ'}},
        ], {operator: 'Иванов'})

        expect(renderBatch).toHaveBeenCalledWith('{{ region ?: city ?: category }}', [
            {id: 1, data: {operator: 'Иванов', region: '', city: 'Москва'}},
            {id: 2, data: {operator: 'Иванов', category: 'СПБ'}},
        ])
        expect(labels.label(1)).toBe('Москва')
        expect(labels.label(2)).toBe('СПБ')
    })

    it('не вызывает API без шаблона', async () => {
        const renderBatch = vi.fn()
        const repository: ExpressionRepository = {render: vi.fn(), renderBatch}
        const labels = useExpressionLabelBatch(repository)

        await labels.load('', [{id: 1, data: {city: 'Москва'}}])

        expect(renderBatch).not.toHaveBeenCalled()
        expect(labels.labels.value).toEqual({})
    })
})
