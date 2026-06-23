export interface VarLike {
    fieldId: string
    blockId: string
    blockTitle: string
    varRef: string
    label?: string
    isCurrent?: boolean
    isAccessor?: boolean
    fieldType?: string
    directoryId?: string
    versionId?: string
    options?: { value: string; label: string }[]
    multiple?: boolean
}

export interface StructureItem { suffix: string; name: string; description: string }
export interface DateFormatItem { format: string; name: string; example: string }
export interface DateShiftItem { duration: string; name: string }
export interface SystemVariable { name: string; label: string }

export const DIRECTORY_FIELD_TYPES = new Set(['directory_list', 'directory_table'])
export const DATE_FIELD_TYPES = new Set(['date', 'datetime'])
export const SELECT_FIELD_TYPE = 'select'

export const STRUCTURE_ITEMS: StructureItem[] = [
    { suffix: 'id',           name: 'ID записи',         description: 'Уникальный идентификатор строки' },
    { suffix: 'label',        name: 'Label',             description: 'Отрисованное имя записи' },
    { suffix: 'parent_id',    name: 'Родительский ID',   description: 'ID родителя (для деревьев)' },
    { suffix: 'external_key', name: 'Внешний ключ',      description: 'Бизнес-идентификатор записи' },
]

export const DATE_FORMATS: DateFormatItem[] = [
    { format: 'DD.MM.YYYY',         name: 'Дата (RU)',         example: '31.12.2026' },
    { format: 'YYYY-MM-DD',         name: 'ISO дата',          example: '2026-12-31' },
    { format: 'D MMMM YYYY',        name: 'Полная дата',       example: '31 декабря 2026' },
    { format: 'dddd, D MMMM YYYY',  name: 'С днём недели',     example: 'пятница, 31 декабря 2026' },
    { format: 'YYYY',               name: 'Год',               example: '2026' },
    { format: 'MM',                 name: 'Месяц (число)',     example: '12' },
    { format: 'MMMM',               name: 'Месяц (название)',  example: 'декабрь' },
    { format: 'DD',                 name: 'День',              example: '31' },
]

export const DATETIME_FORMATS: DateFormatItem[] = [
    { format: 'DD.MM.YYYY HH:mm',    name: 'Дата и время (RU)', example: '31.12.2026 15:30' },
    { format: 'DD.MM.YYYY HH:mm:ss', name: 'С секундами',       example: '31.12.2026 15:30:45' },
    { format: 'HH:mm',               name: 'Время',             example: '15:30' },
    { format: 'HH:mm:ss',            name: 'Время с секундами', example: '15:30:45' },
]

export const DATE_SHIFTS: DateShiftItem[] = [
    { duration: '1d',   name: '+1 день'   },
    { duration: '-1d',  name: '−1 день'   },
    { duration: '7d',   name: '+7 дней'   },
    { duration: '-7d',  name: '−7 дней'   },
    { duration: '1mo',  name: '+1 месяц'  },
    { duration: '-1mo', name: '−1 месяц'  },
    { duration: '1y',   name: '+1 год'    },
    { duration: '-1y',  name: '−1 год'    },
]

export const DATETIME_SHIFTS: DateShiftItem[] = [
    { duration: '1h',   name: '+1 час'      },
    { duration: '-1h',  name: '−1 час'      },
    { duration: '30m',  name: '+30 минут'   },
    { duration: '-30m', name: '−30 минут'   },
    ...DATE_SHIFTS,
]

export const SYSTEM_VARIABLES: SystemVariable[] = [
    { name: 'run_number_formatted', label: 'Номер опроса (с нулями)' },
    { name: 'run_number',           label: 'Номер опроса'             },
    { name: 'run_created_at',       label: 'Дата создания опроса'     },
    { name: 'run_completed_at',     label: 'Дата окончания опроса'    },
    { name: 'operator_login',       label: 'Логин оператора'          },
    { name: 'operator_name',        label: 'Имя оператора'            },
    { name: 'operator_fio',         label: 'ФИО оператора'            },
    { name: 'project_name',         label: 'Проект'                   },
]

export function extractVarName(varRef: string): string {
    const m = /\{\{\s*(.+?)\s*\}\}/.exec(varRef ?? '')
    return m ? m[1] : ''
}

export function accessorRef(v: VarLike, suffix: string): string {
    return `{{ ${extractVarName(v.varRef)}.${suffix} }}`
}

export function dateFormatRef(v: VarLike, format: string): string {
    return `{{ dateFormat(${extractVarName(v.varRef)}, "${format}") }}`
}

export function addTimeRef(v: VarLike, duration: string): string {
    return `{{ addTime(${extractVarName(v.varRef)}, "${duration}") }}`
}

export function implodeRef(v: VarLike): string {
    return `{{ implode(", ", ${extractVarName(v.varRef)}) }}`
}

export function systemVarRef(v: SystemVariable): string {
    return `{{ ${v.name} }}`
}

export function isDirectoryVar(v: VarLike): boolean {
    return !v.isAccessor && !!v.fieldType && DIRECTORY_FIELD_TYPES.has(v.fieldType)
}

export function isDateVar(v: VarLike): boolean {
    return !v.isAccessor && !!v.fieldType && DATE_FIELD_TYPES.has(v.fieldType)
}

export function isSelectVar(v: VarLike): boolean {
    return !v.isAccessor && v.fieldType === SELECT_FIELD_TYPE
}

export function hasHints(v: VarLike): boolean {
    return isDirectoryVar(v) || isDateVar(v) || isSelectVar(v)
}

export function dateFormatsFor(v: VarLike): DateFormatItem[] {
    return v.fieldType === 'datetime' ? [...DATETIME_FORMATS, ...DATE_FORMATS] : DATE_FORMATS
}

export function dateShiftsFor(v: VarLike): DateShiftItem[] {
    return v.fieldType === 'datetime' ? DATETIME_SHIFTS : DATE_SHIFTS
}
