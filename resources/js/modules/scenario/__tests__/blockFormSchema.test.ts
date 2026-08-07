import {describe, expect, it} from 'vitest'
import {buildBlockFormSchema} from '@/modules/scenario/schemas/blockFormSchema'
import type {SurveyBlock} from '@/modules/scenario/lib/scenario-player-types'

function block(type: string, props: Record<string, unknown> = {}): SurveyBlock {
    return {id: `field-${type}`, type, props} as SurveyBlock
}

describe('buildBlockFormSchema', () => {
    it('проверяет обязательные поля во вложенных группах', () => {
        const schema = buildBlockFormSchema([{
            id: 'group',
            type: 'group',
            children: [block('input', {name: 'customer_name', required: true})],
        } as SurveyBlock])

        expect(schema.safeParse({customer_name: ''}).success).toBe(false)
        expect(schema.safeParse({customer_name: 'Иван'}).success).toBe(true)
    })

    it('применяет стратегию проверки VIN', () => {
        const schema = buildBlockFormSchema([block('vin', {name: 'vin', required: true})])

        expect(schema.safeParse({vin: 'INVALID'}).success).toBe(false)
        expect(schema.safeParse({vin: 'JH4TB2H26CC000000'}).success).toBe(true)
    })

    it('проверяет минимальное количество выбранных значений', () => {
        const schema = buildBlockFormSchema([block('select', {name: 'colors', required: true, multiple: true})])

        expect(schema.safeParse({colors: []}).success).toBe(false)
        expect(schema.safeParse({colors: ['red']}).success).toBe(true)
    })
})
