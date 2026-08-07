import {describe, expect, it} from 'vitest'
import {
    buildScenarioFieldNode,
    computeFieldInsertPosition,
    hasTiptapDocumentContent,
    insertBeforeTrailingEmptyParagraph,
    isEmptyParagraphNode,
    syncScenarioFieldPresentations,
    tiptapDocumentPlainText,
} from '@/modules/scenario/lib/tiptap-gutenberg-doc'
import type {BlockField} from '@/modules/scenario/lib/scenario-block-fields'

describe('hasTiptapDocumentContent', () => {
    it('отличает пустой документ от документа с текстом', () => {
        expect(hasTiptapDocumentContent({type: 'doc', content: [{type: 'paragraph'}]})).toBe(false)
        expect(hasTiptapDocumentContent({
            type: 'doc',
            content: [{type: 'paragraph', content: [{type: 'text', text: 'Вопрос'}]}],
        })).toBe(true)
    })

    it('не считает пробелы содержимым', () => {
        expect(hasTiptapDocumentContent({
            type: 'doc',
            content: [{type: 'paragraph', content: [{type: 'text', text: '   '}]}],
        })).toBe(false)
    })

    it('безопасно обрабатывает повреждённые дочерние узлы', () => {
        expect(hasTiptapDocumentContent({type: 'doc', content: [null, 'text']})).toBe(false)
    })
})

describe('tiptapDocumentPlainText', () => {
    it('собирает компактный текст для превью без Tiptap renderer', () => {
        expect(tiptapDocumentPlainText({
            type: 'doc',
            content: [
                {type: 'heading', content: [{type: 'text', text: 'Проверка'}]},
                {type: 'paragraph', content: [{type: 'text', text: '  условия  '}]},
            ],
        })).toBe('Проверка условия')
    })

    it('безопасно обрабатывает пустое и повреждённое содержимое', () => {
        expect(tiptapDocumentPlainText(null)).toBe('')
        expect(tiptapDocumentPlainText({type: 'doc', content: [null, 'text']})).toBe('')
    })
})

describe('isEmptyParagraphNode', () => {
    it('распознаёт параграф без content как пустой', () => {
        expect(isEmptyParagraphNode({type: 'paragraph'})).toBe(true)
    })

    it('распознаёт параграф с пустым массивом content как пустой', () => {
        expect(isEmptyParagraphNode({type: 'paragraph', content: []})).toBe(true)
    })

    it('не считает параграф с текстом пустым', () => {
        expect(isEmptyParagraphNode({type: 'paragraph', content: [{type: 'text', text: 'привет'}]})).toBe(false)
    })

    it('не считает узлы других типов параграфом', () => {
        expect(isEmptyParagraphNode({type: 'scenarioField'})).toBe(false)
    })

    it('обрабатывает undefined', () => {
        expect(isEmptyParagraphNode(undefined)).toBe(false)
    })
})

describe('insertBeforeTrailingEmptyParagraph', () => {
    it('вставляет новые узлы перед завершающим пустым параграфом', () => {
        const content = [
            {type: 'scenarioField', attrs: {fieldId: 'a'}},
            {type: 'paragraph'},
        ]
        const newNodes = [{type: 'scenarioField', attrs: {fieldId: 'b'}}]

        expect(insertBeforeTrailingEmptyParagraph(content, newNodes)).toEqual([
            {type: 'scenarioField', attrs: {fieldId: 'a'}},
            {type: 'scenarioField', attrs: {fieldId: 'b'}},
            {type: 'paragraph'},
        ])
    })

    it('добавляет узлы в конец, если завершающий узел не пустой параграф', () => {
        const content = [{type: 'scenarioField', attrs: {fieldId: 'a'}}]
        const newNodes = [{type: 'scenarioField', attrs: {fieldId: 'b'}}]

        expect(insertBeforeTrailingEmptyParagraph(content, newNodes)).toEqual([
            {type: 'scenarioField', attrs: {fieldId: 'a'}},
            {type: 'scenarioField', attrs: {fieldId: 'b'}},
        ])
    })

    it('добавляет узлы в конец, если завершающий параграф непустой', () => {
        const content = [{type: 'paragraph', content: [{type: 'text', text: 'x'}]}]
        const newNodes = [{type: 'scenarioField', attrs: {fieldId: 'b'}}]

        expect(insertBeforeTrailingEmptyParagraph(content, newNodes)).toEqual([
            {type: 'paragraph', content: [{type: 'text', text: 'x'}]},
            {type: 'scenarioField', attrs: {fieldId: 'b'}},
        ])
    })

    it('работает с пустым исходным content', () => {
        expect(insertBeforeTrailingEmptyParagraph([], [{type: 'scenarioField', attrs: {fieldId: 'a'}}])).toEqual([
            {type: 'scenarioField', attrs: {fieldId: 'a'}},
        ])
    })
})

describe('computeFieldInsertPosition', () => {
    it('вставляет перед завершающим пустым параграфом', () => {
        const fieldNodeSize = 20
        const emptyParagraphNodeSize = 2

        const pos = computeFieldInsertPosition({
            contentSize: fieldNodeSize + emptyParagraphNodeSize,
            lastChild: {typeName: 'paragraph', contentSize: 0, nodeSize: emptyParagraphNodeSize},
        })

        expect(pos).toBe(fieldNodeSize)
    })

    it('вставляет в конец, если последний узел — не пустой параграф', () => {
        const pos = computeFieldInsertPosition({
            contentSize: 20,
            lastChild: {typeName: 'scenarioField', contentSize: 6, nodeSize: 20},
        })

        expect(pos).toBe(20)
    })

    it('вставляет в конец, если последний параграф непустой', () => {
        const pos = computeFieldInsertPosition({
            contentSize: 24,
            lastChild: {typeName: 'paragraph', contentSize: 4, nodeSize: 6},
        })

        expect(pos).toBe(24)
    })

    it('вставляет в конец пустого документа', () => {
        const pos = computeFieldInsertPosition({contentSize: 0, lastChild: null})

        expect(pos).toBe(0)
    })
})

describe('syncScenarioFieldPresentations', () => {
    it('обновляет устаревший label в Tiptap из актуального поля', () => {
        const document = {
            type: 'doc',
            content: [
                {
                    type: 'scenarioField',
                    attrs: {fieldId: 'field_1'},
                    content: [{type: 'text', text: 'Старое название'}],
                },
                {type: 'paragraph', content: [{type: 'text', text: 'Описание'}]},
            ],
        }
        const field = {
            id: 'field_1',
            label: 'Новое название',
            labelFontSize: '16px',
            labelColor: '#334155',
            labelHighlight: '#fef08a',
        } as BlockField

        const result = syncScenarioFieldPresentations(document, [field])

        expect(result).not.toBe(document)
        expect(result.content?.[0]).toEqual(buildScenarioFieldNode(field))
        expect(result.content?.[1]).toBe(document.content[1])
    })

    it('не создаёт новую версию документа, когда label уже синхронизирован', () => {
        const field = {id: 'field_1', label: 'Название'} as BlockField
        const document = {
            type: 'doc',
            content: [buildScenarioFieldNode(field), {type: 'paragraph'}],
        }

        expect(syncScenarioFieldPresentations(document, [field])).toBe(document)
    })
})
