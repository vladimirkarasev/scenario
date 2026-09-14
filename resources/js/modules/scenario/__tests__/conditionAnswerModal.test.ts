import {describe, expect, it, vi} from 'vitest'
import {useConditionAnswerModal} from '@/modules/scenario/composables/useConditionAnswerModal'
import type {ConditionBranch} from '@/modules/scenario/lib/scenario-flow-document'

const answer: ConditionBranch = {
    id: 'answer-1',
    label: 'Да',
    icon: 'check',
    condition: '',
    action: 'transition',
    url: '',
    width: 'half',
    priority: 2,
}

describe('useConditionAnswerModal', () => {
    it('не синхронизирует граф во время ввода и применяет изменения один раз по сохранению', async () => {
        const onSave = vi.fn()
        const modal = useConditionAnswerModal(onSave, vi.fn())

        modal.show(answer)
        modal.form.label = 'Продолжить'
        modal.form.condition = '{{ allowed }}'
        modal.form.priority = 5

        expect(onSave).not.toHaveBeenCalled()

        await modal.save()

        expect(onSave).toHaveBeenCalledOnce()
        expect(onSave).toHaveBeenCalledWith('answer-1', expect.objectContaining({
            label: 'Продолжить',
            condition: '{{ allowed }}',
            priority: 5,
        }))
    })
})
