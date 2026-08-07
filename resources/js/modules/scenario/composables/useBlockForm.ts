import {computed, reactive, watch} from 'vue'
import type {Ref} from 'vue'
import type {SurveyBlock} from '@/modules/scenario/lib/scenario-player-types'
import {buildBlockFormSchema} from '@/modules/scenario/schemas/blockFormSchema'
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

    const schema = computed(() => buildBlockFormSchema(blocks.value))

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
