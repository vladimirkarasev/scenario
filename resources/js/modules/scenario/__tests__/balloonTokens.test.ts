import {describe, expect, it} from 'vitest'
import {resolveBalloonTokens} from '@/modules/scenario/lib/balloon-tokens'

describe('resolveBalloonTokens', () => {
    it('подставляет значение колонки по прямому lookup', () => {
        const doc = {type: 'doc', content: [{type: 'paragraph', content: [{type: 'text', text: 'Тел: {{ phone }}'}]}]}

        const result = resolveBalloonTokens(doc, {phone: '+7 999 000-00-00'}) as typeof doc

        expect(result.content[0].content[0].text).toBe('Тел: +7 999 000-00-00')
    })

    it('подставляет несколько токенов в одной строке', () => {
        const doc = {type: 'doc', content: [{type: 'paragraph', content: [{type: 'text', text: '{{ city }}, {{ street }}'}]}]}

        const result = resolveBalloonTokens(doc, {city: 'Москва', street: 'Тверская'}) as typeof doc

        expect(result.content[0].content[0].text).toBe('Москва, Тверская')
    })

    it('заменяет отсутствующий в data ключ на пустую строку', () => {
        const doc = {type: 'doc', content: [{type: 'paragraph', content: [{type: 'text', text: 'Тел: {{ phone }}'}]}]}

        const result = resolveBalloonTokens(doc, {}) as typeof doc

        expect(result.content[0].content[0].text).toBe('Тел: ')
    })

    it('не трогает текст без токенов', () => {
        const doc = {type: 'doc', content: [{type: 'paragraph', content: [{type: 'text', text: 'Обычный текст'}]}]}

        const result = resolveBalloonTokens(doc, {phone: '123'}) as typeof doc

        expect(result.content[0].content[0].text).toBe('Обычный текст')
    })

    it('обрабатывает вложенные узлы рекурсивно', () => {
        const doc = {
            type: 'doc',
            content: [{
                type: 'bulletList',
                content: [{
                    type: 'listItem',
                    content: [{type: 'paragraph', content: [{type: 'text', text: '{{ name }}'}]}],
                }],
            }],
        }

        const result = resolveBalloonTokens(doc, {name: 'Дилер №1'}) as typeof doc

        expect(result.content[0].content[0].content[0].content[0].text).toBe('Дилер №1')
    })

    it('возвращает исходное значение, если это не объект', () => {
        expect(resolveBalloonTokens(null, {})).toBeNull()
        expect(resolveBalloonTokens(undefined, {})).toBeUndefined()
    })
})
