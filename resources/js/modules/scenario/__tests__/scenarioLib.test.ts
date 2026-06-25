import {describe, expect, it} from 'vitest'
import {extractTemplateKeys, renderLabelTemplate} from '@/modules/scenario/lib/directory-template'
import {
    createEmptyScenarioFlowDocument,
    normalizeScenarioFlowDocument,
    toVueFlowState,
} from '@/modules/scenario/lib/scenario-flow-document'
import {fieldsToVariableEntries} from '@/modules/scenario/lib/scenario-variables'
import {blockFieldsToSurveyBlocks, blockFieldToSurveyBlock} from '@/modules/scenario/lib/block-field-to-survey-block'
import type {BlockField} from '@/modules/scenario/lib/scenario-block-fields'

describe('scenario library', () => {
    it('рендерит плоские поля и пути из context', () => {
        expect(renderLabelTemplate(
            '{{ name }} — {{ operator.fio }} — {{ missing }}',
            {name: 'Москва'},
            {operator: {fio: 'Иванов И.И.'}},
        )).toBe('Москва — Иванов И.И. — ')
        expect(extractTemplateKeys('{{ name }} {{ operator.fio }} {{ name }}')).toEqual([
            'name',
            'operator.fio',
            'name',
        ])
    })

    it('нормализует пустой и legacy flow document', () => {
        expect(normalizeScenarioFlowDocument(null)).toEqual(createEmptyScenarioFlowDocument())

        const flow = normalizeScenarioFlowDocument({
            nodes: [{id: 'start', type: 'start', position: {x: 10, y: 20}, data: {}}],
            edges: [{id: 'edge', source: 'start', target: 'end'}],
            position: [5, 6],
            zoom: 2,
        })

        expect(flow).toMatchObject({
            format: 'scenario-flow',
            version: 1,
            viewport: {x: 5, y: 6, zoom: 2},
            blocks: [{id: 'start', type: 'start', position: {x: 10, y: 20}}],
            connections: [{
                id: 'edge',
                source: {blockId: 'start', port: null},
                target: {blockId: 'end', port: null},
            }],
        })
    })

    it('отбрасывает неполные connections при преобразовании во Vue Flow', () => {
        const state = toVueFlowState({
            blocks: [],
            connections: [
                {id: 'valid', source: {blockId: 'a'}, target: {blockId: 'b'}},
                {id: 'invalid', source: {blockId: ''}, target: {blockId: 'b'}},
            ],
        })
        expect(state.edges.map(edge => edge.id)).toEqual(['valid'])
    })

    it('создаёт переменные только для полей данных', () => {
        const fields = [
            {id: 'text', type: 'rich_text', varName: 'ignored', label: 'Text'},
            {id: 'empty', type: 'text', varName: '', label: 'Empty'},
            {
                id: 'city',
                type: 'select',
                varName: 'city',
                label: 'Город',
                options: [{value: 'msk', label: 'Москва'}],
                multiple: true,
            },
        ] as BlockField[]

        expect(fieldsToVariableEntries(fields, 'block-1', 'Контакты', true)).toEqual([{
            fieldId: 'city',
            blockId: 'block-1',
            blockTitle: 'Контакты',
            varRef: '{{ city }}',
            label: 'Город',
            isCurrent: true,
            isAccessor: false,
            accessorOf: null,
            fieldType: 'select',
            options: [{value: 'msk', label: 'Москва'}],
            multiple: true,
        }])
    })

    it('преобразует поля в survey blocks и исключает actions', () => {
        const numberField = {
            id: 'age',
            type: 'number',
            name: 'age',
            label: 'Возраст',
            required: true,
            varName: 'age',
            placeholder: '',
            value: 18,
            min: 0,
            max: 120,
            step: 1,
            decimalPlaces: 0,
            validation: [{id: 'min', type: 'min', value: '0', message: 'Не меньше нуля'}],
        } as BlockField
        expect(blockFieldToSurveyBlock(numberField)).toMatchObject({
            id: 'age',
            type: 'number',
            props: {
                defaultValue: '18',
                validation: [{id: 'min', type: 'min', value: '0', message: 'Не меньше нуля'}],
            },
        })
        expect(blockFieldsToSurveyBlocks([
            numberField,
            {id: 'action', type: 'action'} as unknown as BlockField,
            {id: 'actions', type: 'action_list'} as unknown as BlockField,
        ])).toHaveLength(1)
    })
})
