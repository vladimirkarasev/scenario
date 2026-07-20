export type BlockFieldType =
    'input'
    | 'email'
    | 'phone'
    | 'textarea'
    | 'rich_text'
    | 'number'
    | 'select'
    | 'date'
    | 'datetime'
    | 'checkbox'
    | 'hidden'
    | 'collapse'
    | 'action'
    | 'action_list'
    | 'directory_list'
    | 'directory_table'
    | 'suggest'

export type ValidationRuleType = 'minLength' | 'maxLength' | 'pattern' | 'min' | 'max'

export interface ValidationRule {
    id: string
    type: ValidationRuleType
    value: string
    message: string
}

interface BaseBlockField {
    id: string
    type: BlockFieldType
    name: string
    label: string
    required: boolean
    varName: string
    validation?: ValidationRule[]
    // Размер/цвет/заливка заголовка поля, задаются через bubble-menu в едином
    // tiptap-документе редактора блока (BlockEditorGutenbergEditor) —
    // применяются как в предпросмотре, так и в реальном опросе. Пусто = дефолт.
    labelFontSize?: string
    labelColor?: string
    labelHighlight?: string
}

export function labelToVarName(label: string): string {
    return label.trim().replace(/\s+/g, '_')
}

export interface InputBlockField extends BaseBlockField {
    type: 'input'
    placeholder: string
    value: string
}

export interface EmailBlockField extends BaseBlockField {
    type: 'email'
    placeholder: string
    value: string
}

export interface PhoneBlockField extends BaseBlockField {
    type: 'phone'
    placeholder: string
    value: string
}

export interface TextareaBlockField extends BaseBlockField {
    type: 'textarea'
    placeholder: string
    value: string
    rows: number
    maxLength: number
}

export interface RichTextBlockField extends BaseBlockField {
    type: 'rich_text'
    value: unknown
}

export interface NumberBlockField extends BaseBlockField {
    type: 'number'
    placeholder: string
    value: number | null
    min: number | null
    max: number | null
    step: number | null
    decimalPlaces: number
}

export interface SelectBlockFieldOption {
    id: string
    label: string
    value: string
    parentId: string | null
}

export interface SelectBlockField extends BaseBlockField {
    type: 'select'
    value: string | string[]
    multiple: boolean
    allowRootSelection: boolean
    defaultSearch: string
    options: SelectBlockFieldOption[]
}

export interface DateBlockField extends BaseBlockField {
    type: 'date'
    value: string
    defaultMode: 'fixed' | 'expression'
    format: string
}

export interface DateTimeBlockField extends BaseBlockField {
    type: 'datetime'
    value: string
    defaultMode: 'fixed' | 'expression'
    format: string
}

export interface CheckboxBlockField extends BaseBlockField {
    type: 'checkbox'
    checked: boolean
}

export interface HiddenBlockField extends BaseBlockField {
    type: 'hidden'
    value: string
}

export interface CollapseBlockField extends BaseBlockField {
    type: 'collapse'
    value: unknown
    defaultCollapsed: boolean
}

export interface ActionBlockField extends BaseBlockField {
    type: 'action'
    actionId: string
    fieldValues: Record<string, string>
    waitForCompletion: boolean
}

export interface ActionListItem {
    id: string
    actionId: string
    fieldValues: Record<string, string>
    waitForCompletion: boolean
    varName: string
}

export interface ActionListBlockField extends BaseBlockField {
    type: 'action_list'
    actions: ActionListItem[]
}

export interface DirectoryListDepDrop {
    fieldVarName: string
    filterKey: string
    valueKey: string
}

export interface DirectoryListBlockField extends BaseBlockField {
    type: 'directory_list'
    directoryId: string
    versionId: string
    labelTemplate: string
    multiple: boolean
    allowRootSelection: boolean
    defaultSearch: string
    depDrop: DirectoryListDepDrop | null
}

export interface DirectoryTableFieldConfig {
    key: string
    visible: boolean
    // Стартовое значение фильтра по этой колонке (используется когда filterMode='template'
    // или для простых текстовых колонок). Поддерживает {{ Var }}; backend резолвит шаблон в
    // payload, фронт применяет полученное значение при открытии диалога. Пусто = не задан.
    defaultValue: string
    // Показывать ли фильтр-чип в UI выбора (юзер может его менять).
    filterable: boolean
    // Закрепить фильтр: применить значение по умолчанию, скрыть chip — юзер не сможет убрать.
    lockFilter: boolean
    // Режим стартового значения фильтра: 'literal' — выбрано из реальных опций справочника,
    // 'template' — строковый шаблон с переменными. По умолчанию 'literal'.
    filterMode?: 'literal' | 'template'
    // Для list-multi колонок в режиме literal: массив выбранных значений.
    filterValues?: string[]
}

export interface DirectoryTableBlockField extends BaseBlockField {
    type: 'directory_table'
    directoryId: string
    versionId: string
    labelTemplate: string
    allowSelection: boolean
    multiple: boolean
    fields: DirectoryTableFieldConfig[]
    defaultSearch: string
}

export interface SuggestBlockField extends BaseBlockField {
    type: 'suggest'
    // UUID прикреплённого proxy-эндпоинта (type=suggest), который отдаёт варианты.
    proxyUuid: string
    // Ключ объекта-варианта, показываемый чипом/в списке. Пусто = первый ключ объекта.
    labelField: string
    placeholder: string
    multiple: boolean
}

export type BlockField =
    | InputBlockField
    | EmailBlockField
    | PhoneBlockField
    | TextareaBlockField
    | RichTextBlockField
    | NumberBlockField
    | SelectBlockField
    | DateBlockField
    | DateTimeBlockField
    | CheckboxBlockField
    | HiddenBlockField
    | CollapseBlockField
    | ActionBlockField
    | ActionListBlockField
    | DirectoryListBlockField
    | DirectoryTableBlockField
    | SuggestBlockField

function uid(prefix: string): string {
    return `${prefix}_${Math.random().toString(36).slice(2, 10)}`
}

function pad(n: number): string {
    return String(n).padStart(2, '0')
}

function currentDateValue(): string {
    const d = new Date()
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}

function currentDateTimeValue(): string {
    const d = new Date()
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

export function createScenarioBlockField(type: BlockFieldType, index = 0): BlockField {
    const id = uid(`${type}_${index}`)
    const n = index + 1

    const byType: Record<BlockFieldType, BlockField> = {
        input: {
            id,
            type: 'input',
            name: id,
            label: 'Текст',
            required: false,
            placeholder: '',
            value: '',
            varName: labelToVarName('Текст')
        },
        email: {
            id,
            type: 'email',
            name: id,
            label: 'Email',
            required: false,
            placeholder: '',
            value: '',
            varName: labelToVarName('Email')
        },
        phone: {
            id,
            type: 'phone',
            name: id,
            label: 'Телефон',
            required: false,
            placeholder: '',
            value: '',
            varName: labelToVarName('Телефон')
        },
        textarea: {
            id,
            type: 'textarea',
            name: id,
            label: 'Textarea',
            required: false,
            placeholder: '',
            value: '',
            rows: 4,
            maxLength: 3000,
            varName: labelToVarName('Textarea')
        },
        rich_text: {
            id,
            type: 'rich_text',
            name: id,
            label: 'Редактор',
            required: false,
            value: {type: 'doc', content: [{type: 'paragraph'}]},
            varName: ''
        },
        number: {
            id,
            type: 'number',
            name: id,
            label: 'Число',
            required: false,
            placeholder: '',
            value: null,
            min: null,
            max: null,
            step: null,
            decimalPlaces: 0,
            varName: labelToVarName('Число')
        },
        select: {
            id,
            type: 'select',
            name: id,
            label: 'Список',
            required: false,
            value: '',
            multiple: false,
            allowRootSelection: true,
            defaultSearch: '',
            options: [{id: uid('select_root'), label: 'Корень', value: 'root', parentId: null}],
            varName: labelToVarName('Список')
        },
        date: {
            id,
            type: 'date',
            name: id,
            label: 'Дата',
            required: false,
            value: currentDateValue(),
            defaultMode: 'fixed',
            format: 'DD.MM.YYYY',
            varName: labelToVarName('Дата')
        },
        datetime: {
            id,
            type: 'datetime',
            name: id,
            label: 'Дата и время',
            required: false,
            value: currentDateTimeValue(),
            defaultMode: 'fixed',
            format: 'DD.MM.YYYY HH:mm',
            varName: labelToVarName('Дата и время')
        },
        checkbox: {
            id,
            type: 'checkbox',
            name: id,
            label: 'Checkbox',
            required: false,
            checked: false,
            varName: labelToVarName('Checkbox')
        },
        hidden: {
            id,
            type: 'hidden',
            name: id,
            label: '',
            required: false,
            value: '',
            varName: `hidden_${n}`
        },
        collapse: {
            id,
            type: 'collapse',
            name: '',
            label: 'Подробнее',
            required: false,
            value: {type: 'doc', content: [{type: 'paragraph'}]},
            defaultCollapsed: false,
            varName: ''
        },
        action: {
            id,
            type: 'action',
            name: id,
            label: 'Действие',
            required: false,
            actionId: '',
            fieldValues: {},
            waitForCompletion: false,
            varName: ''
        },
        action_list: {
            id,
            type: 'action_list',
            name: id,
            label: 'Список действий',
            required: false,
            actions: [],
            varName: ''
        },
        directory_list: {
            id,
            type: 'directory_list',
            name: id,
            label: 'Справочник',
            required: false,
            directoryId: '',
            versionId: '',
            labelTemplate: '',
            multiple: false,
            allowRootSelection: true,
            defaultSearch: '',
            depDrop: null,
            varName: labelToVarName('Справочник')
        },
        directory_table: {
            id,
            type: 'directory_table',
            name: id,
            label: 'Справочник',
            required: false,
            directoryId: '',
            versionId: '',
            labelTemplate: '',
            fields: [],
            allowSelection: false,
            multiple: false,
            defaultSearch: '',
            varName: labelToVarName('Справочник')
        },
        suggest: {
            id,
            type: 'suggest',
            name: id,
            label: 'Подсказки',
            required: false,
            proxyUuid: '',
            labelField: '',
            placeholder: '',
            multiple: false,
            varName: labelToVarName('Подсказки')
        },
    }

    return structuredClone(byType[type] ?? byType.input)
}

const VALID_RULE_TYPES = new Set<ValidationRuleType>(['minLength', 'maxLength', 'pattern', 'min', 'max'])

function normalizeBase(f: Record<string, unknown>, base: BlockField): BaseBlockField {
    const label = String(f.label ?? base.label)
    const validation: ValidationRule[] = Array.isArray(f.validation)
        ? (f.validation as Record<string, unknown>[])
            .filter((r) => VALID_RULE_TYPES.has(r.type as ValidationRuleType))
            .map((r) => ({
                id: String(r.id ?? uid('rule')),
                type: r.type as ValidationRuleType,
                value: String(r.value ?? ''),
                message: String(r.message ?? ''),
            }))
        : []
    // "Ключ поля" не редактируется пользователем — всегда равен id (реальный
    // бэкенд-ключ хранения ответа, см. BlockNodeHandler::continueFrom).
    const id = String(f.id ?? base.id)

    return {
        ...base,
        id,
        name: id,
        label,
        required: f.required === true || f.required === 1,
        varName: f.varName !== undefined ? String(f.varName) : labelToVarName(label),
        validation,
        labelFontSize: typeof f.labelFontSize === 'string' && f.labelFontSize ? f.labelFontSize : undefined,
    }
}

function toNullableNum(v: unknown): number | null {
    if (v === null || v === undefined || v === '') return null
    const n = Number(v)
    return Number.isFinite(n) ? n : null
}

function normalizeDateMode(value: unknown, fallback: DateTimeBlockField['defaultMode']): DateTimeBlockField['defaultMode'] {
    if (value === 'picker' || value === 'fixed') return 'fixed'
    if (value === 'text' || value === 'expression') return 'expression'
    return fallback
}

// Дублирует поля блока с новыми id (включая вложенные validation[].id и,
// для select, options[].id вместе с ремапом parentId на новые id опций;
// actionId/action_id внутри полей не трогаем — это ссылки на реальные Action).
// name ("Ключ поля") и varName ("Название переменной") тоже получают уникальный
// суффикс: varName участвует в плоской карте шаблонов {{ }} на уровне всего
// сценария (ScenarioVariableMapBuilder), совпадение — последняя запись молча
// затирает предыдущую; name — ключ сохранения ответа внутри одного блока.
export function duplicateBlockFieldIds(fields: BlockField[]): BlockField[] {
    return fields.map((field): BlockField => {
        const newId = uid(field.type)
        const suffix = newId.slice(newId.lastIndexOf('_') + 1)
        // name всегда равен id (см. normalizeBase) — дублированное поле просто получает новый id.
        const name = field.name ? newId : field.name
        const varName = field.varName ? `${field.varName}_${suffix}` : field.varName

        const validation = field.validation
            ? field.validation.map((rule) => ({...rule, id: uid('rule')}))
            : field.validation

        if (field.type === 'select') {
            const optionIdMap = new Map<string, string>()
            const optionsWithNewIds = field.options.map((option) => {
                const newOptionId = uid('select_option')
                optionIdMap.set(option.id, newOptionId)
                return {...option, id: newOptionId}
            })

            return {
                ...field,
                id: newId,
                name,
                varName,
                validation,
                options: optionsWithNewIds.map((option) => ({
                    ...option,
                    parentId: option.parentId ? optionIdMap.get(option.parentId) ?? null : null,
                })),
            }
        }

        if (field.type === 'action_list') {
            return {
                ...field,
                id: newId,
                name,
                varName,
                validation,
                actions: field.actions.map((item) => ({...item, id: uid('ali')})),
            }
        }

        return {...field, id: newId, name, varName, validation}
    })
}

export function normalizeScenarioBlockField(field: unknown, index = 0): BlockField {
    const f = ((field as Record<string, unknown>) ?? {})
    const type = String(f.type ?? 'input') as BlockFieldType
    const base = createScenarioBlockField(type, index)
    const nb = normalizeBase(f, base)

    switch (type) {
        case 'input':
        case 'email':
        case 'phone':
            return {
                ...nb,
                placeholder: String(f.placeholder ?? (base as InputBlockField).placeholder),
                value: String(f.value ?? (base as InputBlockField).value)
            } as InputBlockField | EmailBlockField | PhoneBlockField

        case 'textarea': {
            const maxLen = f.maxLength !== undefined ? Number(f.maxLength) : (base as TextareaBlockField).maxLength
            return {
                ...nb,
                placeholder: String(f.placeholder ?? (base as TextareaBlockField).placeholder),
                value: String(f.value ?? (base as TextareaBlockField).value),
                rows: Number(f.rows ?? (base as TextareaBlockField).rows),
                maxLength: maxLen > 0 ? maxLen : 3000
            } as TextareaBlockField
        }

        case 'rich_text':
            return {...nb, value: f.value ?? (base as RichTextBlockField).value, varName: ''} as RichTextBlockField

        case 'number': {
            const raw = f.value === null || f.value === undefined || f.value === '' ? null : Number(f.value)
            const dp = f.decimalPlaces !== undefined ? Math.max(0, Math.round(Number(f.decimalPlaces))) : 0
            return {
                ...nb,
                placeholder: String(f.placeholder ?? (base as NumberBlockField).placeholder),
                value: Number.isFinite(raw) ? raw : null,
                min: toNullableNum(f.min),
                max: toNullableNum(f.max),
                step: toNullableNum(f.step),
                decimalPlaces: Number.isFinite(dp) ? dp : 0,
            } as NumberBlockField
        }

        case 'select': {
            const multiple = Boolean(f.multiple ?? (base as SelectBlockField).multiple)
            const rawOptions = Array.isArray(f.options) ? f.options : (base as SelectBlockField).options
            return {
                ...nb,
                multiple,
                allowRootSelection: Boolean(f.allowRootSelection ?? (base as SelectBlockField).allowRootSelection),
                defaultSearch: String(f.defaultSearch ?? ''),
                value: multiple
                    ? (Array.isArray(f.value) ? f.value.map(String) : String(f.value ?? '').split(',').map((i) => i.trim()).filter(Boolean))
                    : String(Array.isArray(f.value) ? (f.value[0] ?? '') : (f.value ?? '')),
                options: rawOptions.map((opt: unknown, i: number) => {
                    const o = (opt && typeof opt === 'object') ? opt as Record<string, unknown> : {}
                    return {
                        id: String(o.id ?? uid(`select_option_${i}`)),
                        label: String(o.label ?? `Option ${i + 1}`),
                        value: String(o.value ?? `option_${i + 1}`),
                        parentId: o.parentId ? String(o.parentId) : null,
                    }
                }),
            } as SelectBlockField
        }

        case 'date':
        case 'datetime':
            return {
                ...nb,
                value: String(f.value ?? (base as DateTimeBlockField).value),
                defaultMode: normalizeDateMode(f.defaultMode, (base as DateTimeBlockField).defaultMode),
                format: String(f.format ?? (base as DateTimeBlockField).format)
            } as DateBlockField | DateTimeBlockField

        case 'checkbox':
            return {...nb, checked: Boolean(f.checked ?? (base as CheckboxBlockField).checked)} as CheckboxBlockField

        case 'hidden':
            return {
                ...nb,
                label: '',
                required: false,
                value: String(f.value ?? (base as HiddenBlockField).value),
                varName: f.varName !== undefined ? String(f.varName) : `hidden_${index + 1}`
            } as HiddenBlockField

        case 'collapse':
            return {
                ...nb,
                name: '',
                required: false,
                value: f.value ?? (base as CollapseBlockField).value,
                defaultCollapsed: Boolean(f.defaultCollapsed ?? (base as CollapseBlockField).defaultCollapsed),
                varName: ''
            } as CollapseBlockField

        case 'action': {
            const rawFv = f.fieldValues ?? f.field_values
            const fieldValues = (rawFv && typeof rawFv === 'object' && !Array.isArray(rawFv))
                ? Object.fromEntries(Object.entries(rawFv as Record<string, unknown>).map(([k, v]) => [k, String(v)]))
                : {}
            return {
                ...nb,
                actionId: String(f.actionId ?? f.action_id ?? ''),
                fieldValues,
                waitForCompletion: Boolean(f.waitForCompletion ?? f.wait_for_completion ?? false),
                varName: ''
            } as ActionBlockField
        }

        case 'action_list': {
            const rawActions = Array.isArray(f.actions) ? f.actions : []
            const actions: ActionListItem[] = rawActions.map((item: unknown) => {
                const it = (item && typeof item === 'object') ? item as Record<string, unknown> : {}
                const rawFv = it.fieldValues ?? it.field_values
                const fv = (rawFv && typeof rawFv === 'object' && !Array.isArray(rawFv))
                    ? Object.fromEntries(Object.entries(rawFv as Record<string, unknown>).map(([k, v]) => [k, String(v)]))
                    : {}
                return {
                    id: String(it.id ?? uid('ali')),
                    actionId: String(it.actionId ?? it.action_id ?? ''),
                    fieldValues: fv,
                    waitForCompletion: Boolean(it.waitForCompletion ?? it.wait_for_completion ?? false),
                    varName: String(it.varName ?? '')
                }
            })
            return {...nb, actions, varName: ''} as ActionListBlockField
        }

        case 'directory_list': {
            // backward-compat: camelCase preferred, snake_case legacy; label_field → {{ key }} template
            const rawTpl = f.labelTemplate ?? f.label_template
            const labelTemplate = rawTpl ? String(rawTpl) : (f.label_field ? `{{ ${f.label_field} }}` : '')
            const rawAllow = f.allowRootSelection ?? f.allow_root_selection
            const rawDepDrop = f.depDrop ?? f.dep_drop
            const depDrop: DirectoryListDepDrop | null =
                rawDepDrop && typeof rawDepDrop === 'object' && !Array.isArray(rawDepDrop)
                    ? {
                        fieldVarName: String((rawDepDrop as Record<string, unknown>).fieldVarName ?? ''),
                        filterKey: String((rawDepDrop as Record<string, unknown>).filterKey ?? ''),
                        valueKey: String((rawDepDrop as Record<string, unknown>).valueKey ?? 'external_key'),
                    }
                    : null
            return {
                ...nb,
                directoryId: String(f.directoryId ?? f.directory_id ?? ''),
                versionId: String(f.versionId ?? f.version_id ?? ''),
                labelTemplate,
                multiple: Boolean(f.multiple ?? false),
                allowRootSelection: rawAllow !== undefined ? Boolean(rawAllow) : true,
                defaultSearch: String(f.defaultSearch ?? f.default_search ?? ''),
                depDrop,
            } as DirectoryListBlockField
        }

        case 'directory_table': {
            const fields: DirectoryTableFieldConfig[] = Array.isArray(f.fields)
                ? f.fields.map((c: Record<string, unknown>) => ({
                    key: String(c.key ?? ''),
                    visible: Boolean(c.visible ?? true),
                    defaultValue: String(c.defaultValue ?? c.default_value ?? ''),
                    filterable: Boolean(c.filterable ?? false),
                    lockFilter: Boolean(c.lockFilter ?? c.lock_filter ?? false),
                    filterMode: (c.filterMode ?? c.filter_mode) === 'template' ? 'template' : 'literal',
                    filterValues: (() => {
                        const raw = c.filterValues ?? c.filter_values
                        return Array.isArray(raw)
                            ? raw.filter((v): v is string => typeof v === 'string')
                            : []
                    })(),
                }))
                : []
            return {
                ...nb,
                directoryId: String(f.directoryId ?? f.directory_id ?? ''),
                versionId: String(f.versionId ?? f.version_id ?? ''),
                labelTemplate: String(f.labelTemplate ?? f.label_template ?? ''),
                allowSelection: Boolean(f.allowSelection ?? f.allow_selection ?? false),
                multiple: Boolean(f.multiple ?? false),
                fields,
                defaultSearch: String(f.defaultSearch ?? f.default_search ?? '')
            } as DirectoryTableBlockField
        }

        case 'suggest':
            return {
                ...nb,
                proxyUuid: String(f.proxyUuid ?? f.proxy_uuid ?? ''),
                labelField: String(f.labelField ?? f.label_field ?? ''),
                placeholder: String(f.placeholder ?? (base as SuggestBlockField).placeholder),
                multiple: Boolean(f.multiple ?? false),
            } as SuggestBlockField

        default:
            return nb as BlockField
    }
}
