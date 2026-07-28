import {describe, expect, it} from 'vitest'
import {
    computeEnterInScenarioFieldAction,
    ScenarioField,
    SCENARIO_FIELD_NODE_NAME,
    shouldConsumeEnterInScenarioField,
} from '@/lib/tiptap-scenario-field'

describe('shouldConsumeEnterInScenarioField', () => {
    it('перехватывает Enter, когда курсор внутри лейбла поля', () => {
        expect(shouldConsumeEnterInScenarioField(SCENARIO_FIELD_NODE_NAME)).toBe(true)
    })

    it('пропускает Enter в остальных узлах документа', () => {
        expect(shouldConsumeEnterInScenarioField('paragraph')).toBe(false)
        expect(shouldConsumeEnterInScenarioField('heading')).toBe(false)
    })
})

describe('computeEnterInScenarioFieldAction', () => {
    it('в конце лейбла — вставляет параграф после поля и переводит туда фокус', () => {
        const action = computeEnterInScenarioFieldAction({
            parentOffset: 5,
            parentContentSize: 5,
            beforePos: 10,
            afterPos: 20,
        })

        expect(action).toEqual({insertAt: 20, focusAt: 21})
    })

    it('в начале лейбла — вставляет параграф перед полем и не трогает фокус', () => {
        const action = computeEnterInScenarioFieldAction({
            parentOffset: 0,
            parentContentSize: 5,
            beforePos: 10,
            afterPos: 20,
        })

        expect(action).toEqual({insertAt: 10, focusAt: null})
    })

    it('в середине лейбла — ведёт себя как в начале (параграф перед полем)', () => {
        const action = computeEnterInScenarioFieldAction({
            parentOffset: 2,
            parentContentSize: 5,
            beforePos: 10,
            afterPos: 20,
        })

        expect(action).toEqual({insertAt: 10, focusAt: null})
    })

    it('пустой лейбл — считается концом (параграф после + фокус туда)', () => {
        const action = computeEnterInScenarioFieldAction({
            parentOffset: 0,
            parentContentSize: 0,
            beforePos: 10,
            afterPos: 12,
        })

        expect(action).toEqual({insertAt: 12, focusAt: 13})
    })
})

describe('ScenarioField priority', () => {
    it('выше приоритета core-расширения Keymap (100), иначе наш Enter/Backspace не перехватит событие первым', () => {
        expect(ScenarioField.config.priority ?? 100).toBeGreaterThan(100)
    })
})
