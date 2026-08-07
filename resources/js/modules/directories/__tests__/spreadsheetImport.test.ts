import {describe, expect, it} from 'vitest'
import {
    MAX_SPREADSHEET_BYTES,
    spreadsheetColumnsError,
    spreadsheetFileError,
} from '@/modules/directories/lib/spreadsheetImport'

describe('spreadsheetFileError', () => {
    it('принимает поддерживаемый файл в пределах лимита', () => {
        expect(spreadsheetFileError({name: 'items.xlsx', size: 1024})).toBeNull()
    })

    it('отклоняет неподдерживаемый формат', () => {
        expect(spreadsheetFileError({name: 'payload.html', size: 1024})).toContain('CSV, XLS или XLSX')
    })

    it('отклоняет слишком большой файл', () => {
        expect(spreadsheetFileError({name: 'items.xlsx', size: MAX_SPREADSHEET_BYTES + 1})).toContain('10 МБ')
    })
})

describe('spreadsheetColumnsError', () => {
    it('ограничивает чрезмерно широкие таблицы', () => {
        expect(spreadsheetColumnsError(201)).not.toBeNull()
        expect(spreadsheetColumnsError(200)).toBeNull()
    })
})
