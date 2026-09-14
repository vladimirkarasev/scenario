import {describe, expect, it} from 'vitest'
import {
    isContentNodeType,
    SCENARIO_TYPE_OPTIONS,
    SCENARIO_TYPE_VALUES,
} from '@/modules/scenario/lib/scenario-types'

describe('scenario types', () => {
    it('содержит поддерживаемые типы и подписи', () => {
        expect(SCENARIO_TYPE_VALUES).toEqual(['colls', 'telegram', 'watsapp', 'call_bots'])
        expect(SCENARIO_TYPE_OPTIONS.map((item) => item.label)).toEqual([
            'Звонки',
            'Телеграм',
            'Ватсап',
            'Боты звонков',
        ])
    })

    it('отличает контентные узлы', () => {
        expect(isContentNodeType('block')).toBe(true)
        expect(isContentNodeType('question')).toBe(true)
        expect(isContentNodeType('action')).toBe(false)
    })
})
