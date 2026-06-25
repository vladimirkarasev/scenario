import {describe, expect, it} from 'vitest'
import {actionInputFieldSchema, actionSchema} from '@/modules/actions/schemas/actionSchema'
import {actionRunSchema} from '@/modules/actions/schemas/actionRunSchema'
import {actionScheduleSchema} from '@/modules/actions/schemas/actionScheduleSchema'
import {actionSectionSchema} from '@/modules/actions/schemas/actionSectionSchema'

describe('action schemas', () => {
    it('принимает корректное действие', () => {
        const result = actionSchema.safeParse({
            name: 'Отправка письма',
            slug: 'send-email',
            code: 'mail.send',
            description: '',
            type: 'service',
            is_active: true,
            config: {},
            input_fields: [{
                key: 'recipient_email',
                label: 'Email',
                type: 'email',
                required: true,
                default: null,
            }],
            category_ids: ['category-1'],
        })

        expect(result.success).toBe(true)
    })

    it.each(['', '1field', 'Field', 'field-name'])('отклоняет недопустимый ключ поля: %s', (key) => {
        expect(actionInputFieldSchema.safeParse({
            key,
            label: '',
            type: 'string',
            required: false,
            default: null,
        }).success).toBe(false)
    })

    it.each(['', 'Слаг', '-action'])('отклоняет недопустимый slug: %s', (slug) => {
        expect(actionSchema.safeParse({
            name: 'Action',
            slug,
            code: '',
            description: '',
            type: 'service',
            is_active: true,
            config: {},
            input_fields: [],
            category_ids: [],
        }).success).toBe(false)
    })

    it('проверяет обязательные поля расписания', () => {
        expect(actionScheduleSchema.safeParse({
            enabled: true,
            cron: '',
            timezone: '',
            input: {},
            options: {},
            settings: {},
        }).success).toBe(false)
    })

    it('принимает input запуска и раздел с nullable parent', () => {
        expect(actionRunSchema.safeParse({input: {id: 10}}).success).toBe(true)
        expect(actionSectionSchema.safeParse({name: 'Раздел', parent_id: null}).success).toBe(true)
    })
})
