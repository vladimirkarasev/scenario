import {describe, expect, it} from 'vitest'
import {buildBlockNodePreviewItems} from '@/modules/scenario/lib/block-node-preview'
import type {BlockField} from '@/modules/scenario/lib/scenario-block-fields'

const fields = [
    {
        id: 'name',
        type: 'input',
        name: 'name',
        label: 'Имя',
        required: true,
        varName: 'name',
        placeholder: 'Введите имя',
        value: '',
    },
    {
        id: 'age',
        type: 'number',
        name: 'age',
        label: 'Возраст',
        required: false,
        varName: 'age',
        placeholder: '',
        value: null,
        min: null,
        max: null,
        step: 1,
        decimalPlaces: 0,
    },
] satisfies BlockField[]

describe('block node preview', () => {
    it('сохраняет порядок текста и полей из Tiptap-документа', () => {
        const items = buildBlockNodePreviewItems({
            type: 'doc',
            content: [
                {type: 'heading', attrs: {level: 2}, content: [{type: 'text', text: 'Анкета'}]},
                {type: 'scenarioField', attrs: {fieldId: 'age'}},
                {type: 'paragraph', content: [{type: 'text', text: 'Укажите имя'}]},
                {type: 'scenarioField', attrs: {fieldId: 'name'}},
            ],
        }, fields)

        expect(items.map((item) => item.type)).toEqual(['text', 'field', 'text', 'field'])
        expect(items.filter((item) => item.type === 'field').map((item) => item.field.id)).toEqual(['age', 'name'])
    })

    it('не возвращает удалённое поле, оставшееся в Tiptap-документе', () => {
        const items = buildBlockNodePreviewItems({
            type: 'doc',
            content: [
                {type: 'scenarioField', attrs: {fieldId: 'removed'}},
                {type: 'scenarioField', attrs: {fieldId: 'name'}},
            ],
        }, fields)

        expect(items).toHaveLength(1)
        expect(items[0]).toMatchObject({type: 'field', field: {id: 'name'}})
    })

    it('показывает поля по их порядку, пока Tiptap-документ ещё не создан', () => {
        const items = buildBlockNodePreviewItems(null, fields)

        expect(items.map((item) => item.type === 'field' ? item.field.id : '')).toEqual(['name', 'age'])
    })
})
