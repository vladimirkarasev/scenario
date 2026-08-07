import {describe, expect, it, vi} from 'vitest'
import {useSelectExpressionPreview} from '@/modules/scenario/composables/useSelectExpressionPreview'
import type {ExpressionRepository} from '@/modules/expression/types/expression'
import type {VarLike} from '@/modules/scenario/lib/scenario-variable-hints'

function selectVariable(): VarLike {
    return {
        fieldId: 'category',
        blockId: 'block-1',
        blockTitle: 'Блок',
        varRef: '{{ Категории }}',
        fieldType: 'select',
        multiple: true,
        options: [
            {value: 'first', label: 'Первый'},
            {value: 'second', label: 'Второй'},
            {value: 'third', label: 'Третий'},
            {value: 'fourth', label: 'Четвёртый'},
        ],
    }
}

describe('useSelectExpressionPreview', () => {
    it('получает пример выражения через repository', async () => {
        const render = vi.fn().mockResolvedValue({rendered: 'Первый, Второй, Третий'})
        const repository: ExpressionRepository = {render, renderBatch: vi.fn()}
        const expressionPreview = useSelectExpressionPreview(repository)

        await expressionPreview.load(selectVariable())

        expect(render).toHaveBeenCalledWith('{{ implode(", ", Категории) }}', {
            Категории: ['Первый', 'Второй', 'Третий'],
        })
        expect(expressionPreview.preview.value).toBe('Первый, Второй, Третий')
        expect(expressionPreview.error.value).toBeNull()
    })

    it('не вызывает API без опций для примера', async () => {
        const render = vi.fn()
        const repository: ExpressionRepository = {render, renderBatch: vi.fn()}
        const expressionPreview = useSelectExpressionPreview(repository)
        const variable = selectVariable()
        variable.options = []

        await expressionPreview.load(variable)

        expect(render).not.toHaveBeenCalled()
        expect(expressionPreview.preview.value).toBe('')
    })
})
