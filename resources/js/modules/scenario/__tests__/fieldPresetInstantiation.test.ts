import {beforeEach, describe, expect, it} from 'vitest'
import {createPinia, setActivePinia} from 'pinia'
import {useScenarioBlockEditorStore} from '@/modules/scenario/stores/scenarioBlockEditor'

describe('scenario field preset instantiation', () => {
    beforeEach(() => setActivePinia(createPinia()))

    it('создаёт независимые id и уникальные переменные для каждой вставки', async () => {
        const store = useScenarioBlockEditorStore()
        store.initialize('scenario-1', null, 'block-1')
        await store.load()

        const presetField = {
            type: 'select',
            name: 'city',
            label: 'Город',
            required: false,
            varName: 'city',
            value: [],
            multiple: true,
            allowRootSelection: true,
            defaultSearch: '',
            validation: [{id: 'rule-1', type: 'minLength', value: '1', message: ''}],
            options: [
                {id: 'root', label: 'Россия', value: 'ru', parentId: null},
                {id: 'child', label: 'Москва', value: 'msk', parentId: 'root'},
            ],
        }

        const first = store.addFieldFromPreset(presetField)
        const second = store.addFieldFromPreset(presetField)

        expect(first?.id).not.toBe(second?.id)
        expect(first?.varName).toBe('city')
        expect(second?.varName).toBe('city_2')

        if (first?.type !== 'select' || second?.type !== 'select') throw new Error('Expected select fields.')
        expect(first.options[0].id).not.toBe(second.options[0].id)
        expect(first.options[1].parentId).toBe(first.options[0].id)
        expect(second.options[1].parentId).toBe(second.options[0].id)
        expect(first.validation?.[0].id).not.toBe(second.validation?.[0].id)
    })
})
