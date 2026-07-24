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
    proxyUuid?: string
}

export interface StructureItem {
    suffix: string;
    name: string;
    description: string
}

export interface DateFormatItem {
    format: string;
    name: string;
    example: string
}

export interface DateShiftItem {
    duration: string;
    name: string
}

export interface SystemVariableField {
    suffix: string;
    label: string;
    description: string
}

export interface SystemVariableGroup {
    name: string;
    label: string;
    fields: SystemVariableField[]
}

export const DIRECTORY_FIELD_TYPES = new Set(['directory_list', 'directory_table'])
export const DATE_FIELD_TYPES = new Set(['date', 'datetime'])
export const SELECT_FIELD_TYPE = 'select'
export const PHONE_FIELD_TYPE = 'phone'
export const SUGGEST_FIELD_TYPE = 'suggest'

export const PHONE_ACCESSORS: StructureItem[] = [
    {suffix: 'formatted', name: 'Форматированный', description: '+7 (999) 999-99-99'},
    {suffix: 'original', name: 'Цифры с кодом страны', description: '79999999999'},
    {suffix: 'national', name: 'Без кода страны', description: '9999999999'},
    {suffix: 'country', name: 'Код страны', description: 'ISO-код, напр. RU'},
]

export const STRUCTURE_ITEMS: StructureItem[] = [
    {suffix: 'id', name: 'ID записи', description: 'Уникальный идентификатор строки'},
    {suffix: 'label', name: 'Label', description: 'Отрисованное имя записи'},
    {suffix: 'parent_id', name: 'Родительский ID', description: 'ID родителя (для деревьев)'},
    {suffix: 'external_key', name: 'Внешний ключ', description: 'Бизнес-идентификатор записи'},
]

export const DATE_FORMATS: DateFormatItem[] = [
    {format: 'DD.MM.YYYY', name: 'Дата (RU)', example: '31.12.2026'},
    {format: 'YYYY-MM-DD', name: 'ISO дата', example: '2026-12-31'},
    {format: 'D MMMM YYYY', name: 'Полная дата', example: '31 декабря 2026'},
    {format: 'dddd, D MMMM YYYY', name: 'С днём недели', example: 'пятница, 31 декабря 2026'},
    {format: 'YYYY', name: 'Год', example: '2026'},
    {format: 'MM', name: 'Месяц (число)', example: '12'},
    {format: 'MMMM', name: 'Месяц (название)', example: 'декабрь'},
    {format: 'DD', name: 'День', example: '31'},
]

export const DATETIME_FORMATS: DateFormatItem[] = [
    {format: 'DD.MM.YYYY HH:mm', name: 'Дата и время (RU)', example: '31.12.2026 15:30'},
    {format: 'DD.MM.YYYY HH:mm:ss', name: 'С секундами', example: '31.12.2026 15:30:45'},
    {format: 'HH:mm', name: 'Время', example: '15:30'},
    {format: 'HH:mm:ss', name: 'Время с секундами', example: '15:30:45'},
]

export const DATE_SHIFTS: DateShiftItem[] = [
    {duration: '1d', name: '+1 день'},
    {duration: '-1d', name: '−1 день'},
    {duration: '7d', name: '+7 дней'},
    {duration: '-7d', name: '−7 дней'},
    {duration: '1mo', name: '+1 месяц'},
    {duration: '-1mo', name: '−1 месяц'},
    {duration: '1y', name: '+1 год'},
    {duration: '-1y', name: '−1 год'},
]

export const DATETIME_SHIFTS: DateShiftItem[] = [
    {duration: '1h', name: '+1 час'},
    {duration: '-1h', name: '−1 час'},
    {duration: '30m', name: '+30 минут'},
    {duration: '-30m', name: '−30 минут'},
    ...DATE_SHIFTS,
]

export const SYSTEM_VARIABLE_GROUPS: SystemVariableGroup[] = [
    {
        name: 'run',
        label: 'Опрос',
        fields: [
            {suffix: 'id', label: 'UUID опроса', description: 'Уникальный идентификатор прогона'},
            {suffix: 'number', label: 'Номер опроса', description: 'Порядковый номер'},
            {suffix: 'number_formatted', label: 'Номер опроса (с нулями)', description: 'Номер с ведущими нулями'},
            {suffix: 'created_at', label: 'Дата создания опроса', description: 'Когда опрос был начат'},
            {suffix: 'completed_at', label: 'Дата окончания опроса', description: 'Когда опрос был завершён'},
        ],
    },
    {
        name: 'operator',
        label: 'Оператор',
        fields: [
            {suffix: 'login', label: 'Логин оператора', description: 'Логин учётной записи'},
            {suffix: 'name', label: 'Имя оператора', description: 'Отображаемое имя'},
            {suffix: 'fio', label: 'ФИО оператора', description: 'Полное имя'},
        ],
    },
    {
        name: 'project',
        label: 'Проект',
        fields: [
            {suffix: 'name', label: 'Название проекта', description: 'Имя проекта оператора'},
            {suffix: 'id', label: 'ID проекта', description: 'Идентификатор проекта'},
        ],
    },
    {
        name: 'call',
        label: 'Звонок',
        fields: [
            {suffix: 'incoming_phone', label: 'Входящий номер телефона', description: 'Номер входящего звонка'},
            {suffix: 'outgoing_phone', label: 'Исходящий номер телефона', description: 'Номер исходящего звонка'},
            {suffix: 'internal_phone', label: 'Внутренний номер', description: 'Внутренний номер сотрудника'},
            {suffix: 'id', label: 'Идентификатор звонка', description: 'Уникальный идентификатор звонка'},
        ],
    },
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

export function systemGroupRef(group: SystemVariableGroup): string {
    return `{{ ${group.name} }}`
}

export function systemFieldRef(group: SystemVariableGroup, field: SystemVariableField): string {
    return `{{ ${group.name}.${field.suffix} }}`
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

export function isPhoneVar(v: VarLike): boolean {
    return !v.isAccessor && v.fieldType === PHONE_FIELD_TYPE
}

export function isSuggestVar(v: VarLike): boolean {
    return !v.isAccessor && v.fieldType === SUGGEST_FIELD_TYPE
}

export function hasHints(v: VarLike): boolean {
    return isDirectoryVar(v) || isDateVar(v) || isSelectVar(v) || isPhoneVar(v) || isSuggestVar(v)
}

export function dateFormatsFor(v: VarLike): DateFormatItem[] {
    return v.fieldType === 'datetime' ? [...DATETIME_FORMATS, ...DATE_FORMATS] : DATE_FORMATS
}

export function dateShiftsFor(v: VarLike): DateShiftItem[] {
    return v.fieldType === 'datetime' ? DATETIME_SHIFTS : DATE_SHIFTS
}
