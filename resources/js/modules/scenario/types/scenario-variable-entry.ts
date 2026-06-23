import type {BlockFieldType} from '../lib/scenario-block-fields'

// ─── Base ─────────────────────────────────────────────────────────────────────

interface BaseVariableEntry {
    fieldId: string
    blockId: string
    blockTitle: string
    varRef: string       // {{ varName }}
    label: string
    isCurrent: boolean
}

// ─── Discriminated union ──────────────────────────────────────────────────────

export interface MainVariableEntry extends BaseVariableEntry {
    isAccessor: false
    accessorOf: null
    fieldType: BlockFieldType
    // Для directory_list / directory_table — чтобы UI мог подтянуть schema колонок.
    directoryId?: string
    versionId?: string
    // Для select — список опций и multiple-флаг, чтобы UI мог показать подсказки.
    options?: { value: string; label: string }[]
    multiple?: boolean
}

export interface AccessorVariableEntry extends BaseVariableEntry {
    isAccessor: true
    accessorOf: string   // fieldId родительской переменной
    suffix: string       // 'ts', 'keys', 'format:DD.MM.YYYY' и т.д.
    description: string  // человекочитаемое описание: 'timestamp', 'значения (ID)', ...
}

export type VariableEntry = MainVariableEntry | AccessorVariableEntry

// ─── Accessor descriptors per field type ─────────────────────────────────────

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
