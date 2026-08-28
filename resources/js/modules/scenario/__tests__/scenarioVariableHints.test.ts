import {describe, expect, it} from 'vitest'
import {
    accessorRef,
    addTimeRef,
    dateFormatsFor,
    dateFormatRef,
    dateShiftsFor,
    extractVarName,
    hasHints,
    implodeRef,
    isDateVar,
    isDirectoryVar,
    isSelectVar,
    systemFieldRef,
    systemGroupRef,
    type VarLike,
} from '@/modules/scenario/lib/scenario-variable-hints'
import {
    isReservedScenarioVariable,
    isSystemVariable,
    ScenarioContextKey,
} from '@/modules/scenario/types/scenario-context-key'
import type {SystemVariableGroup} from '@/modules/scenario/types/scenario-system-variable'

function variable(patch: Partial<VarLike> = {}): VarLike {
    return {
        fieldId: 'field-1',
        blockId: 'block-1',
        blockTitle: 'Контакты',
        varRef: '{{ customer }}',
        ...patch,
    }
}

describe('scenario variable hints', () => {
    it('строит выражения для переменных', () => {
        const value = variable()

        expect(extractVarName('  {{ customer.name }}  ')).toBe('customer.name')
        expect(extractVarName('customer')).toBe('')
        expect(accessorRef(value, 'label')).toBe('{{ customer.label }}')
        expect(dateFormatRef(value, 'DD.MM.YYYY')).toBe('{{ dateFormat(customer, "DD.MM.YYYY") }}')
        expect(addTimeRef(value, '-1d')).toBe('{{ addTime(customer, "-1d") }}')
        expect(implodeRef(value)).toBe('{{ implode(", ", customer) }}')
    })

    it('определяет типы переменных с подсказками', () => {
        expect(isDirectoryVar(variable({fieldType: 'directory_list'}))).toBe(true)
        expect(isDateVar(variable({fieldType: 'datetime'}))).toBe(true)
        expect(isSelectVar(variable({fieldType: 'select'}))).toBe(true)
        expect(hasHints(variable({fieldType: 'string'}))).toBe(false)
        expect(hasHints(variable({fieldType: 'date', isAccessor: true}))).toBe(false)
    })

    it('возвращает расширенные форматы и сдвиги только для datetime', () => {
        const date = variable({fieldType: 'date'})
        const datetime = variable({fieldType: 'datetime'})

        expect(dateFormatsFor(date).some(item => item.format === 'HH:mm')).toBe(false)
        expect(dateFormatsFor(datetime).some(item => item.format === 'HH:mm')).toBe(true)
        expect(dateShiftsFor(date).some(item => item.duration === '1h')).toBe(false)
        expect(dateShiftsFor(datetime).some(item => item.duration === '1h')).toBe(true)
    })

    it('строит ссылки на системные переменные', () => {
        const group: SystemVariableGroup = {
            name: '_run',
            label: 'Опрос',
            fields: [{suffix: 'id', label: 'UUID опроса', description: 'Идентификатор'}],
        }
        const field = group.fields[0]

        expect(systemGroupRef(group)).toBe('{{ _run }}')
        expect(systemFieldRef(group, field)).toBe('{{ _run.id }}')
    })

    it('отличает системные и зарезервированные имена', () => {
        expect(Object.values(ScenarioContextKey).every(isSystemVariable)).toBe(true)
        expect(isReservedScenarioVariable(ScenarioContextKey.Run)).toBe(true)
        expect(isReservedScenarioVariable('_custom')).toBe(false)
        expect(isSystemVariable('_custom')).toBe(true)
        expect(isSystemVariable('varName')).toBe(false)
    })
})
