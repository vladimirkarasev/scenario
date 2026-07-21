import {computed, reactive, watch} from 'vue'
import {z} from 'zod'
import type {Ref} from 'vue'
import type {SurveyBlock} from '@/modules/scenario/lib/scenario-player-types'
import type {ValidationRule} from '@/modules/scenario/lib/scenario-block-fields'

const TYPE_LABELS: Record<string, string> = {
    string: 'строка',
    number: 'число',
    bigint: 'число',
    boolean: 'логическое значение',
    date: 'дата',
    array: 'список',
    object: 'объект',
    null: 'null',
    undefined: 'значение',
    nan: 'число',
}

z.setErrorMap((issue, ctx) => {
    switch (issue.code) {
        case z.ZodIssueCode.invalid_type: {
            if (issue.received === 'undefined' || issue.received === 'null') {
                return {message: 'Поле обязательно для заполнения'}
            }
            const expected = TYPE_LABELS[issue.expected] ?? issue.expected
            const received = TYPE_LABELS[issue.received] ?? issue.received
            return {message: `Ожидается ${expected}, получено ${received}`}
        }
        case z.ZodIssueCode.too_small:
            if (issue.type === 'string' && Number(issue.minimum) === 1) return {message: 'Поле обязательно для заполнения'}
            if (issue.type === 'string') return {message: `Минимум ${issue.minimum} символов`}
            if (issue.type === 'array') return {message: `Минимум ${issue.minimum} элементов`}
            return {message: `Минимальное значение: ${issue.minimum}`}
        case z.ZodIssueCode.too_big:
            if (issue.type === 'string') return {message: `Максимум ${issue.maximum} символов`}
            if (issue.type === 'array') return {message: `Максимум ${issue.maximum} элементов`}
            return {message: `Максимальное значение: ${issue.maximum}`}
        case z.ZodIssueCode.invalid_string:
            if (issue.validation === 'email') return {message: 'Введите корректный email адрес'}
            if (issue.validation === 'url') return {message: 'Введите корректный URL'}
            if (issue.validation === 'regex') return {message: 'Неверный формат'}
            return {message: 'Неверный формат строки'}
        case z.ZodIssueCode.invalid_enum_value:
            return {message: 'Недопустимое значение'}
        case z.ZodIssueCode.invalid_union:
            return {message: 'Неверное значение'}
        case z.ZodIssueCode.invalid_literal:
            return {message: 'Неверное значение'}
        default:
            return {message: ctx.defaultError}
    }
})

const FIELD_TYPES = new Set([
    'input', 'email', 'phone', 'textarea', 'number', 'select',
    'date', 'datetime', 'checkbox', 'directory_list', 'directory_table',
])

function fieldName(block: SurveyBlock): string {
    return String(block.props?.name ?? block.props?.key ?? block.id)
}

function* iterBlocks(blocks: SurveyBlock[]): Generator<SurveyBlock> {
    for (const b of blocks) {
        if (b.type === 'container' || b.type === 'group') {
            yield* iterBlocks((b.children ?? []) as SurveyBlock[])
        } else {
            yield b
        }
    }
}

function applyStringRules(base: z.ZodString, rules: ValidationRule[]): z.ZodString {
    let s = base
    for (const rule of rules) {
        if (rule.type === 'minLength' && Number(rule.value) > 0)
            s = s.min(Number(rule.value), rule.message || `Минимум ${rule.value} символов`)
        else if (rule.type === 'maxLength' && Number(rule.value) > 0)
            s = s.max(Number(rule.value), rule.message || `Максимум ${rule.value} символов`)
        else if (rule.type === 'pattern' && rule.value)
            try {
                s = s.regex(new RegExp(rule.value), rule.message || 'Неверный формат')
            } catch { /* skip */
            }
    }
    return s
}

function buildFieldSchema(block: SurveyBlock): z.ZodTypeAny {
    const p = block.props ?? {}
    const required = Boolean(p.required)
    const type = block.type
    const rules = Array.isArray(p.validation) ? p.validation as ValidationRule[] : []

    if (type === 'checkbox') {
        return required
            ? z.literal(true, {errorMap: () => ({message: 'Поле обязательно для заполнения'})})
            : z.boolean().nullable().optional()
    }

    if (type === 'email') {
        const emailRules = rules.filter((r) => r.type === 'pattern')
        if (required) {
            return applyStringRules(
                z.string().min(1, 'Поле обязательно для заполнения').email('Введите корректный email адрес'),
                emailRules,
            )
        }
        return z.union([z.literal(''), applyStringRules(z.string().email('Введите корректный email адрес'), emailRules)]).nullable().optional()
    }

    if (type === 'number') {
        const minVal = p.min !== null && p.min !== undefined ? Number(p.min) : null
        const maxVal = p.max !== null && p.max !== undefined ? Number(p.max) : null
        const base = z.union([z.number(), z.string().regex(/^-?\d*\.?\d+$/, 'Значение должно быть числом')])
            .superRefine((val, ctx) => {
                const n = Number(val)
                if (minVal !== null && Number.isFinite(minVal) && n < minVal)
                    ctx.addIssue({code: z.ZodIssueCode.custom, message: `Минимальное значение: ${minVal}`})
                if (maxVal !== null && Number.isFinite(maxVal) && n > maxVal)
                    ctx.addIssue({code: z.ZodIssueCode.custom, message: `Максимальное значение: ${maxVal}`})
            })
        return required ? base : base.or(z.literal('')).nullable().optional()
    }

    if (type === 'textarea') {
        const max = Number(p.maxLength ?? 3000)
        let base = z.string().max(max, `Превышено максимальное количество символов: ${max}`)
        base = applyStringRules(base, rules)
        return required ? base.min(1, 'Поле обязательно для заполнения') : base.or(z.literal('')).nullable().optional()
    }

    if (type === 'date' || type === 'datetime') {
        return required
            ? z.string().min(1, 'Поле обязательно для заполнения')
            : z.string().or(z.literal('')).nullable().optional()
    }

    if (type === 'directory_list') {
        const shape = z.object({
            id: z.string(),
            label: z.string(),
            data: z.record(z.string(), z.union([z.string(), z.null()])),
            parent_id: z.union([z.string(), z.null()]),
            external_key: z.string(),
        })
        if (p.multiple) {
            const arrItem = z.union([shape, z.string()])
            return required
                ? z.array(arrItem).min(1, 'Поле обязательно для заполнения')
                : z.array(arrItem).nullable().optional()
        }
        const single = z.union([shape, z.string()])
        if (required) {
            return single.refine(
                (v) => (typeof v === 'string' ? v.length > 0 : v.id.length > 0),
                'Поле обязательно для заполнения',
            )
        }
        return single.or(z.literal('')).nullable().optional()
    }

    if (type === 'directory_table') {
        const shape = z.object({
            id: z.string(),
            label: z.string(),
            data: z.record(z.string(), z.union([z.string(), z.null()])),
            parent_id: z.union([z.string(), z.null()]),
            external_key: z.string(),
        })
        if (p.multiple) {
            const arrItem = z.union([shape, z.string()])
            return required
                ? z.array(arrItem).min(1, 'Поле обязательно для заполнения')
                : z.array(arrItem).nullable().optional()
        }
        const single = z.union([shape, z.string()])
        if (required) {
            return single.refine(
                (v) => (typeof v === 'string' ? v.length > 0 : v.id.length > 0),
                'Поле обязательно для заполнения',
            )
        }
        return single.or(z.literal('')).nullable().optional()
    }

    if (type === 'select') {
        const shape = z.object({value: z.string(), label: z.string()})
        if (p.multiple) {
            const arrItem = z.union([shape, z.string()])
            return required
                ? z.array(arrItem).min(1, 'Поле обязательно для заполнения')
                : z.array(arrItem).nullable().optional()
        }
        const single = z.union([shape, z.string()])
        if (required) {
            return single.refine(
                (v) => (typeof v === 'string' ? v.length > 0 : v.value.length > 0),
                'Поле обязательно для заполнения',
            )
        }
        return single.or(z.literal('')).nullable().optional()
    }

    if (type === 'phone') {
        const phoneObject = z.object({
            country: z.string(),
            formatted: z.string(),
            original: z.string(),
        })
        if (required) {
            return z.union([
                z.string().min(1, 'Поле обязательно для заполнения'),
                phoneObject.refine((v) => v.original.length > 0, 'Поле обязательно для заполнения'),
            ])
        }
        return z.union([z.string(), phoneObject]).or(z.literal('')).nullable().optional()
    }

    const base = applyStringRules(z.string(), rules)
    return required ? base.min(1, 'Поле обязательно для заполнения') : base.or(z.literal('')).nullable().optional()
}

function buildSchema(blocks: SurveyBlock[]): z.ZodObject<Record<string, z.ZodTypeAny>> {
    const shape: Record<string, z.ZodTypeAny> = {}
    for (const block of iterBlocks(blocks)) {
        if (!FIELD_TYPES.has(block.type)) continue
        shape[fieldName(block)] = buildFieldSchema(block)
    }
    return z.object(shape)
}

export function useBlockForm(
    blocks: Ref<SurveyBlock[]>,
    fieldErrors?: Ref<Record<string, string[]> | undefined>,
    draftKey?: Ref<string | null>,
    initialValues?: Ref<Record<string, unknown> | null | undefined>,
) {
    const formData = reactive<Record<string, unknown>>({})
    const errors = reactive<Record<string, string>>({})

    function loadDraft(): Record<string, unknown> | null {
        const key = draftKey?.value
        if (!key || typeof window === 'undefined') return null
        try {
            const raw = window.localStorage.getItem(key)
            return raw ? (JSON.parse(raw) as Record<string, unknown>) : null
        } catch {
            return null
        }
    }

    function saveDraft(data: Record<string, unknown>): void {
        const key = draftKey?.value
        if (!key || typeof window === 'undefined') return
        try {
            window.localStorage.setItem(key, JSON.stringify(data))
        } catch { /* quota / disabled */
        }
    }

    function clearDraft(): void {
        const key = draftKey?.value
        if (!key || typeof window === 'undefined') return
        try {
            window.localStorage.removeItem(key)
        } catch { /* ignore */
        }
    }

    watch(
        [
            blocks,
            ...(draftKey ? [draftKey] : []),
            ...(initialValues ? [initialValues] : []),
        ],
        () => {
            Object.keys(formData).forEach((k) => delete formData[k])
            Object.keys(errors).forEach((k) => delete errors[k])

            const init = initialValues?.value
            if (init) {
                for (const [k, v] of Object.entries(init)) {
                    formData[k] = v
                }
            }
            const draft = loadDraft()
            if (draft) {
                for (const [k, v] of Object.entries(draft)) {
                    formData[k] = v
                }
            }
        },
        {immediate: true},
    )

    if (fieldErrors) {
        watch(fieldErrors, (errs) => {
            Object.keys(errors).forEach((k) => delete errors[k])
            for (const [key, msgs] of Object.entries(errs ?? {})) {
                if (msgs.length > 0) errors[key] = msgs[0]
            }
        })
    }

    const schema = computed(() => buildSchema(blocks.value))

    watch(formData, (data) => {
        for (const [key, value] of Object.entries(data)) {
            const fieldSchema = schema.value.shape[key]
            if (!fieldSchema) continue
            const isEmpty = value === undefined || value === null || value === '' || (Array.isArray(value) && value.length === 0)
            if (isEmpty && !errors[key]) continue
            const result = fieldSchema.safeParse(value)
            if (result.success) {
                delete errors[key]
            } else {
                errors[key] = result.error.issues[0].message
            }
        }

        saveDraft({...data})
    }, {deep: true})

    function validate(): boolean {
        Object.keys(errors).forEach((k) => delete errors[k])
        const result = schema.value.safeParse(formData)
        if (!result.success) {
            for (const issue of result.error.issues) {
                const key = String(issue.path[0])
                if (!errors[key]) errors[key] = issue.message
            }
            return false
        }
        return true
    }

    function submit(callback: (data: Record<string, unknown>) => void): void {
        if (validate()) {
            const snapshot = {...formData}
            clearDraft()
            callback(snapshot)
        }
    }

    return {formData, errors, validate, submit}
}
