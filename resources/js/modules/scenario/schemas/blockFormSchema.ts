import {z} from 'zod'
import type {SurveyBlock} from '@/modules/scenario/lib/scenario-player-types'
import type {ValidationRule} from '@/modules/scenario/lib/scenario-block-fields'

type FieldSchemaFactory = (block: SurveyBlock, rules: ValidationRule[]) => z.ZodTypeAny

const FIELD_TYPES = new Set([
    'input', 'email', 'phone', 'vin', 'grz', 'textarea', 'number', 'select',
    'date', 'datetime', 'checkbox', 'directory_list', 'directory_table',
])

const VIN_PATTERN = /^[A-HJ-NPR-Z0-9]{17}$/
const GRZ_CHARSET_PATTERN = /^[A-ZА-Я0-9]+$/

const selectableItemSchema = z.object({value: z.string(), label: z.string()})
const directoryItemSchema = z.object({
    id: z.string(),
    label: z.string(),
    data: z.record(z.string(), z.union([z.string(), z.null()])),
    parent_id: z.union([z.string(), z.null()]),
    external_key: z.string(),
})

function fieldName(block: SurveyBlock): string {
    return String(block.props?.name ?? block.props?.key ?? block.id)
}

function* iterBlocks(blocks: SurveyBlock[]): Generator<SurveyBlock> {
    for (const block of blocks) {
        if (block.type === 'container' || block.type === 'group') {
            yield* iterBlocks((block.children ?? []) as SurveyBlock[])
        } else {
            yield block
        }
    }
}

function applyStringRules(base: z.ZodString, rules: ValidationRule[]): z.ZodString {
    let schema = base
    for (const rule of rules) {
        if (rule.type === 'minLength' && Number(rule.value) > 0) {
            schema = schema.min(Number(rule.value), rule.message || `Минимум ${rule.value} символов`)
        } else if (rule.type === 'maxLength' && Number(rule.value) > 0) {
            schema = schema.max(Number(rule.value), rule.message || `Максимум ${rule.value} символов`)
        } else if (rule.type === 'pattern' && rule.value) {
            try {
                schema = schema.regex(new RegExp(rule.value), rule.message || 'Неверный формат')
            } catch {
                continue
            }
        }
    }
    return schema
}

function isRequired(block: SurveyBlock): boolean {
    return Boolean(block.props?.required)
}

function createSelectableSchema(
    block: SurveyBlock,
    itemSchema: z.ZodTypeAny,
    valueKey: 'id' | 'value',
): z.ZodTypeAny {
    const single = z.union([itemSchema, z.string()])
    if (block.props?.multiple) {
        const array = z.array(single)
        return isRequired(block) ? array.min(1, 'Поле обязательно для заполнения') : array.nullable().optional()
    }
    if (!isRequired(block)) return single.or(z.literal('')).nullable().optional()
    return single.refine((value) => {
        if (typeof value === 'string') return value.length > 0
        return typeof value === 'object' && value !== null && valueKey in value
            && String((value as Record<string, unknown>)[valueKey]).length > 0
    }, 'Поле обязательно для заполнения')
}

const fieldSchemaStrategies: Partial<Record<string, FieldSchemaFactory>> = {
    checkbox: (block) => isRequired(block)
        ? z.literal(true, {errorMap: () => ({message: 'Поле обязательно для заполнения'})})
        : z.boolean().nullable().optional(),
    email: (block, rules) => {
        const schema = applyStringRules(z.string().email('Введите корректный email адрес'), rules)
        return isRequired(block)
            ? schema.min(1, 'Поле обязательно для заполнения')
            : z.union([z.literal(''), schema]).nullable().optional()
    },
    number: (block) => {
        const min = block.props?.min === null || block.props?.min === undefined ? null : Number(block.props.min)
        const max = block.props?.max === null || block.props?.max === undefined ? null : Number(block.props.max)
        const schema = z.union([z.number(), z.string().regex(/^-?\d*\.?\d+$/, 'Значение должно быть числом')])
            .superRefine((value, context) => {
                const numeric = Number(value)
                if (min !== null && Number.isFinite(min) && numeric < min) {
                    context.addIssue({code: z.ZodIssueCode.custom, message: `Минимальное значение: ${min}`})
                }
                if (max !== null && Number.isFinite(max) && numeric > max) {
                    context.addIssue({code: z.ZodIssueCode.custom, message: `Максимальное значение: ${max}`})
                }
            })
        return isRequired(block) ? schema : schema.or(z.literal('')).nullable().optional()
    },
    textarea: (block, rules) => {
        const max = Number(block.props?.maxLength ?? 3000)
        const schema = applyStringRules(z.string().max(max, `Превышено максимальное количество символов: ${max}`), rules)
        return isRequired(block) ? schema.min(1, 'Поле обязательно для заполнения') : schema.or(z.literal('')).nullable().optional()
    },
    date: (block) => isRequired(block)
        ? z.string().min(1, 'Поле обязательно для заполнения')
        : z.string().or(z.literal('')).nullable().optional(),
    datetime: (block) => fieldSchemaStrategies.date!(block, []),
    directory_list: (block) => createSelectableSchema(block, directoryItemSchema, 'id'),
    directory_table: (block) => createSelectableSchema(block, directoryItemSchema, 'id'),
    select: (block) => createSelectableSchema(block, selectableItemSchema, 'value'),
    phone: (block) => {
        const object = z.object({country: z.string(), formatted: z.string(), original: z.string()})
        return isRequired(block)
            ? z.union([z.string().min(1, 'Поле обязательно для заполнения'), object.refine(value => value.original.length > 0, 'Поле обязательно для заполнения')])
            : z.union([z.string(), object]).or(z.literal('')).nullable().optional()
    },
    vin: (block) => {
        const message = 'Введите корректный VIN: 17 символов, заглавные латинские буквы (без I, O, Q) и цифры'
        const object = z.object({value: z.string()})
        return isRequired(block)
            ? z.union([z.string().min(1, 'Поле обязательно для заполнения').regex(VIN_PATTERN, message), object.refine(value => VIN_PATTERN.test(value.value), message)])
            : z.union([z.literal(''), z.string().regex(VIN_PATTERN, message), object.refine(value => value.value === '' || VIN_PATTERN.test(value.value), message)]).nullable().optional()
    },
    grz: (block) => {
        const message = 'Номер ГРЗ должен быть в верхнем регистре (буквы и цифры)'
        const object = z.object({country: z.string(), formatted: z.string(), original: z.string()})
        return isRequired(block)
            ? z.union([z.string().min(1, 'Поле обязательно для заполнения').regex(GRZ_CHARSET_PATTERN, message), object.refine(value => value.original.length > 0 && GRZ_CHARSET_PATTERN.test(value.original), message)])
            : z.union([z.literal(''), z.string().regex(GRZ_CHARSET_PATTERN, message), object.refine(value => value.original === '' || GRZ_CHARSET_PATTERN.test(value.original), message)]).nullable().optional()
    },
}

function createDefaultSchema(block: SurveyBlock, rules: ValidationRule[]): z.ZodTypeAny {
    const schema = applyStringRules(z.string(), rules)
    return isRequired(block)
        ? schema.min(1, 'Поле обязательно для заполнения')
        : schema.or(z.literal('')).nullable().optional()
}

export function buildBlockFormSchema(blocks: SurveyBlock[]): z.ZodObject<Record<string, z.ZodTypeAny>> {
    const shape: Record<string, z.ZodTypeAny> = {}
    for (const block of iterBlocks(blocks)) {
        if (!FIELD_TYPES.has(block.type)) continue
        const rules = Array.isArray(block.props?.validation) ? block.props.validation as ValidationRule[] : []
        const strategy = fieldSchemaStrategies[block.type] ?? createDefaultSchema
        shape[fieldName(block)] = strategy(block, rules)
    }
    return z.object(shape)
}
