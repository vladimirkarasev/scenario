import {reactive, ref, toValue, watch} from 'vue'
import type {MaybeRefOrGetter, Ref} from 'vue'
import type {z} from 'zod'
import {HttpValidationError} from '@/lib/http'

export class FormValidationError extends Error {
    constructor(public readonly errors: Record<string, string>) {
        const summary = Object.entries(errors)
            .map(([field, message]) => `${field}: ${message}`)
            .join('; ')
        super(summary ? `Form validation failed — ${summary}` : 'Form validation failed')
        this.name = 'FormValidationError'
    }

    get fields(): string[] {
        return Object.keys(this.errors)
    }
}

export interface UseZodFormReturn<T extends object> {
    formData: T
    errors: Record<string, string>
    formError: Ref<string | null>
    submitting: Ref<boolean>
    validate: () => boolean
    submit: (onValid: (data: T) => Promise<void> | void) => Promise<void>
    reset: (next?: Partial<T>) => void
    setServerErrors: (errors: Record<string, string[]>) => void
    clearError: (key: string) => void
}

export function useZodForm<Schema extends z.ZodType<object>>(
    schema: MaybeRefOrGetter<Schema>,
    initial: z.infer<Schema>,
): UseZodFormReturn<z.infer<Schema>> {
    type T = z.infer<Schema>

    const formData = reactive({...initial}) as T
    const errors = reactive<Record<string, string>>({})
    const formError = ref<string | null>(null)
    const submitting = ref(false)

    watch(formData, (data) => {
        for (const key of Object.keys(errors)) {
            const value = (data as Record<string, unknown>)[key]
            if (value === '' || value === null || value === undefined) continue
            const result = toValue(schema).safeParse(data)
            if (result.success) {
                delete errors[key]
            } else {
                const issue = result.error.issues.find(i => String(i.path[0]) === key)
                if (!issue) delete errors[key]
            }
        }
    }, {deep: true})

    function validate(): boolean {
        for (const k of Object.keys(errors)) delete errors[k]
        formError.value = null

        const result = toValue(schema).safeParse(formData)
        if (result.success) return true

        for (const issue of result.error.issues) {
            const key = String(issue.path[0])
            if (!errors[key]) errors[key] = issue.message
        }
        return false
    }

    function setServerErrors(serverErrors: Record<string, string[]>): void {
        for (const [path, messages] of Object.entries(serverErrors)) {
            const key = path.split('.')[0]
            if (messages.length > 0 && !errors[key]) {
                errors[key] = messages[0]
            }
        }
    }

    async function submit(onValid: (data: T) => Promise<void> | void): Promise<void> {
        if (!validate()) {
            throw new FormValidationError({...errors})
        }
        submitting.value = true
        try {
            await onValid(formData)
        } catch (e: unknown) {
            if (e instanceof HttpValidationError) {
                setServerErrors(e.errors)
                formError.value = e.message
            } else {
                formError.value = e instanceof Error ? e.message : 'Ошибка сохранения'
            }
            throw e
        } finally {
            submitting.value = false
        }
    }

    function reset(next?: Partial<T>): void {
        for (const key of Object.keys(formData as Record<string, unknown>)) {
            delete (formData as Record<string, unknown>)[key]
        }
        Object.assign(formData as Record<string, unknown>, {...initial, ...(next ?? {})})
        for (const k of Object.keys(errors)) delete errors[k]
        formError.value = null
    }

    function clearError(key: string): void {
        delete errors[key]
    }

    return {
        formData,
        errors,
        formError,
        submitting,
        validate,
        submit,
        reset,
        setServerErrors,
        clearError,
    }
}
