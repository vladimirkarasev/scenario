import {describe, expect, it} from 'vitest'
import {isBlockFieldVarNameUnique} from '@/modules/scenario/lib/block-field-validation'
import type {BlockField} from '@/modules/scenario/lib/scenario-block-fields'

function createField(id: string, varName: string): BlockField {
    return {
        id,
        type: 'input',
        name: id,
        label: id,
        required: false,
        varName,
        value: '',
        placeholder: '',
        validation: [],
    }
}

describe('isBlockFieldVarNameUnique', () => {
    it('returns true when the settings field is no longer available', () => {
        expect(isBlockFieldVarNameUnique(null, [createField('name', 'name')])).toBe(true)
    })

    it('ignores unavailable entries while the field collection is changing', () => {
        const selected = createField('city', 'city')

        expect(isBlockFieldVarNameUnique(selected, [undefined, selected, null])).toBe(true)
    })

    it('detects the same variable name on another field', () => {
        const selected = createField('city', 'city')
        const duplicate = createField('region', 'city')

        expect(isBlockFieldVarNameUnique(selected, [selected, duplicate])).toBe(false)
    })
})
