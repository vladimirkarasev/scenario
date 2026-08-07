import {describe, expect, it} from 'vitest'
import {connectionSchema} from '@/modules/proxy/schemas/connectionSchema'

describe('connectionSchema', () => {
    it('принимает корректный доступ', () => {
        expect(connectionSchema.safeParse({
            name: 'CRM production',
            credential_type: 'basic',
            values: {login: 'service'},
        }).success).toBe(true)
    })

    it('отклоняет пустые название и тип', () => {
        const result = connectionSchema.safeParse({name: ' ', credential_type: '', values: {}})

        expect(result.success).toBe(false)
        if (!result.success) {
            expect(result.error.flatten().fieldErrors.name?.[0]).toBe('Название обязательно')
            expect(result.error.flatten().fieldErrors.credential_type?.[0]).toBe('Тип доступа обязателен')
        }
    })
})
