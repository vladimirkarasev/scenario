import type {BlockFieldType} from '../lib/scenario-block-fields'

interface BaseVariableEntry {
    fieldId: string
    blockId: string
    blockTitle: string
    varRef: string
    label: string
    isCurrent: boolean
}

export interface MainVariableEntry extends BaseVariableEntry {
    isAccessor: false
    accessorOf: null
    fieldType: BlockFieldType
    directoryId?: string
    versionId?: string
    options?: { value: string; label: string }[]
    multiple?: boolean
    proxyUuid?: string
}

export interface AccessorVariableEntry extends BaseVariableEntry {
    isAccessor: true
    accessorOf: string
    suffix: string
    description: string
}

export type VariableEntry = MainVariableEntry | AccessorVariableEntry

export interface AccessorDescriptor {
    suffix: string
    description: string
}

export const FIELD_ACCESSORS: Partial<Record<BlockFieldType, AccessorDescriptor[]>> = {
    select: [
        {suffix: 'keys', description: 'значения (ID)'},
        {suffix: 'list', description: 'все опции'},
    ],
    date: [
        {suffix: 'ts', description: 'timestamp'},
        {suffix: 'iso', description: 'ISO дата'},
        {suffix: 'year', description: 'год'},
        {suffix: 'month', description: 'месяц'},
        {suffix: 'day', description: 'день'},
        {suffix: 'format:DD.MM.YYYY', description: 'формат'},
    ],
    datetime: [
        {suffix: 'ts', description: 'timestamp'},
        {suffix: 'iso', description: 'ISO дата/время'},
        {suffix: 'date', description: 'дата'},
        {suffix: 'time', description: 'время'},
        {suffix: 'year', description: 'год'},
        {suffix: 'month', description: 'месяц'},
        {suffix: 'day', description: 'день'},
        {suffix: 'hour', description: 'час'},
        {suffix: 'minute', description: 'минута'},
        {suffix: 'format:DD.MM.YYYY HH:mm', description: 'формат'},
    ],
    directory_list: [
        {suffix: 'keys', description: 'ID записей'},
    ],
    directory_table: [
        {suffix: 'keys', description: 'ID записей'},
    ],
}
