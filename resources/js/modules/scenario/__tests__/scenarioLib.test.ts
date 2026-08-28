import {describe, expect, it} from 'vitest'
import {extractTemplateKeys} from '@/modules/scenario/lib/directory-template'
import {
    blockVariableFromId,
    createEmptyScenarioFlowDocument,
    duplicateScenarioFlowBlocks,
    normalizeScenarioFlowDocument,
    parseScenarioFlowClipboard,
    serializeScenarioFlowClipboard,
    toVueFlowState,
} from '@/modules/scenario/lib/scenario-flow-document'
import {fieldsToVariableEntries} from '@/modules/scenario/lib/scenario-variables'
import {blockFieldsToSurveyBlocks, blockFieldToSurveyBlock} from '@/modules/scenario/lib/block-field-to-survey-block'
import type {BlockField} from '@/modules/scenario/lib/scenario-block-fields'

describe('scenario library', () => {
    it('извлекает поля из простого шаблона подписи', () => {
        expect(extractTemplateKeys('{{ name }} {{ _operator.fio }} {{ name }}')).toEqual([
            'name',
            '_operator.fio',
            'name',
        ])
    })

    it('извлекает ключи-слаги с дефисами', () => {
        expect(extractTemplateKeys('{{ city-name }}')).toEqual(['city-name'])
    })

    it('нормализует пустой и legacy flow document', () => {
        const emptyDocument = normalizeScenarioFlowDocument(null)
        expect(emptyDocument).toEqual(createEmptyScenarioFlowDocument())

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
        expect(flow.blocks[0].data.hideTitle).toBe(true)

        const visibleTitleFlow = normalizeScenarioFlowDocument({
            nodes: [{id: 'start', type: 'start', data: {hideTitle: false}}],
            edges: [],
        })
        expect(visibleTitleFlow.blocks[0].data.hideTitle).toBe(false)
    })

    it('создаёт condition node без вариантов перехода по умолчанию', () => {
        const flow = normalizeScenarioFlowDocument({
            blocks: [{id: 'condition', type: 'condition', data: {}}],
            connections: [],
        })

        expect(flow.blocks[0].data.conditionBranches).toEqual([])
    })

    it('дублирует блоки с новыми id и сдвигом позиции, ремапит только внутренние connections', () => {
        const blocks = [
            {id: 'a', type: 'block', position: {x: 0, y: 0}, data: {title: 'A', variable: 'block_a'}},
            {id: 'b', type: 'block', position: {x: 10, y: 10}, data: {title: 'B', variable: 'block_b'}},
        ]
        const connections = [
            {id: 'a-b', source: {blockId: 'a', port: null}, target: {blockId: 'b', port: null}, label: null, data: {}},
            {id: 'a-outside', source: {blockId: 'a', port: null}, target: {blockId: 'c', port: null}, label: null, data: {}},
        ]

        const result = duplicateScenarioFlowBlocks(blocks as never, connections as never, {x: 40, y: 40})

        expect(result.blocks).toHaveLength(2)
        expect(result.blocks.map((b) => b.id)).not.toEqual(['a', 'b'])
        expect(new Set(result.blocks.map((b) => b.id)).size).toBe(2)
        expect(result.blocks[0].position).toEqual({x: 40, y: 40})
        expect(result.blocks[1].position).toEqual({x: 50, y: 50})

        expect(result.connections).toHaveLength(1)
        expect(result.connections[0].source.blockId).toBe(result.blocks[0].id)
        expect(result.connections[0].target.blockId).toBe(result.blocks[1].id)
        expect(result.connections[0].id).not.toBe('a-b')

        expect(result.blocks[0].data.variable).not.toBe('block_a')
        expect(result.blocks[1].data.variable).not.toBe('block_b')
        expect(result.blocks[0].data.variable).not.toBe(result.blocks[1].data.variable)
        expect(result.blocks[0].data.variable).toBe(blockVariableFromId(result.blocks[0].id))
        expect(result.blocks[1].data.variable).toBe(blockVariableFromId(result.blocks[1].id))
    })

    it('дублирует блоки: регенерирует id вложенных полей, опций select (с ремапом parentId) и action_items', () => {
        const blocks = [{
            id: 'a',
            type: 'block',
            position: {x: 0, y: 0},
            data: {
                title: 'A',
                fields: [
                    {
                        id: 'field_1',
                        type: 'select',
                        name: 'select_1',
                        label: 'L',
                        required: false,
                        varName: 'l',
                        value: '',
                        multiple: false,
                        allowRootSelection: true,
                        defaultSearch: '',
                        validation: [{id: 'rule_1', type: 'minLength', value: '1', message: 'm'}],
                        options: [
                            {id: 'opt_root', label: 'Root', value: 'root', parentId: null},
                            {id: 'opt_child', label: 'Child', value: 'child', parentId: 'opt_root'},
                        ],
                    },
                ],
                conditionBranches: [{id: 'branch_yes', label: 'Да'}],
                action_items: [{id: 'ali_1', code: 'x', action_id: 'real-action-uuid'}],
            },
        }]

        const result = duplicateScenarioFlowBlocks(blocks as never, [], {x: 0, y: 0})
        const field = (result.blocks[0].data.fields[0] as never) as {
            id: string
            name: string
            varName: string
            validation: { id: string }[]
            options: { id: string; parentId: string | null }[]
        }

        expect(field.id).not.toBe('field_1')
        expect(field.name).toBe(field.id)
        expect(field.varName).toMatch(/^l_/)
        expect(field.validation[0].id).not.toBe('rule_1')

        const [root, child] = field.options
        expect(root.id).not.toBe('opt_root')
        expect(child.id).not.toBe('opt_child')
        expect(child.parentId).toBe(root.id)

        expect(result.blocks[0].data.conditionBranches[0].id).not.toBe('branch_yes')

        const actionItems = result.blocks[0].data.action_items as { id: string; action_id: string }[]
        expect(actionItems[0].id).not.toBe('ali_1')
        expect(actionItems[0].action_id).toBe('real-action-uuid')
    })

    it('дублирует condition node вместе со стрелкой от новой кнопки', () => {
        const blocks = [
            {
                id: 'condition',
                type: 'condition',
                position: {x: 0, y: 0},
                data: {
                    conditionBranches: [{
                        id: 'answer',
                        label: 'Дальше',
                        icon: 'check',
                        condition: '',
                        action: 'transition',
                        url: '',
                    }],
                },
            },
            {id: 'target', type: 'block', position: {x: 20, y: 20}, data: {title: 'Target'}},
        ]
        const connections = [{
            id: 'edge',
            source: {blockId: 'condition', port: 'answer'},
            target: {blockId: 'target', port: 'in'},
            label: null,
            data: {},
        }]

        const result = duplicateScenarioFlowBlocks(blocks as never, connections as never, {x: 40, y: 40})
        const answerId = result.blocks[0].data.conditionBranches[0].id

        expect(answerId).not.toBe('answer')
        expect(result.connections[0].source.port).toBe(answerId)
    })

    it('сериализует/парсит буфер обмена нод и отбрасывает чужой JSON', () => {
        const blocks = [{id: 'a', type: 'block', position: {x: 0, y: 0}, data: {title: 'A'}}]
        const connections: unknown[] = []

        const serialized = serializeScenarioFlowClipboard(blocks as never, connections as never)
        const parsed = parseScenarioFlowClipboard(serialized)

        expect(parsed?.blocks).toMatchObject([{id: 'a', type: 'block'}])
        expect(parsed?.connections).toEqual([])

        expect(parseScenarioFlowClipboard('not json')).toBeNull()
        expect(parseScenarioFlowClipboard(JSON.stringify({foo: 'bar'}))).toBeNull()
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
            hideLabel: true,
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
                hideLabel: true,
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
