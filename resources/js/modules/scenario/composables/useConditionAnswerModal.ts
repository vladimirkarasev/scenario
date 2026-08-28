import {ref} from 'vue'
import {useZodForm} from '@/composables/useZodForm'
import {
    conditionAnswerSchema,
    type ConditionAnswerFormValues,
} from '@/modules/scenario/schemas/conditionAnswerSchema'
import type {ConditionBranch} from '@/modules/scenario/lib/scenario-flow-document'

export function useConditionAnswerModal(
    onSave: (answerId: string, values: ConditionAnswerFormValues) => void,
    onRemove: (answerId: string) => void,
) {
    const open = ref(false)
    const answerId = ref<string | null>(null)
    const {formData: form, errors, formError, submitting, submit, reset} = useZodForm(
        conditionAnswerSchema,
        {
            label: '',
            icon: null,
            condition: 'true',
            action: 'transition',
            url: '',
            width: 'full',
            priority: 1,
        },
    )

    function show(answer: ConditionBranch): void {
        answerId.value = answer.id
        reset({
            label: answer.label,
            icon: answer.icon,
            condition: answer.condition,
            action: answer.action,
            url: answer.url,
            width: answer.width,
            priority: answer.priority,
        })
        open.value = true
    }

    function close(): void {
        open.value = false
        answerId.value = null
    }

    async function save(): Promise<void> {
        const currentAnswerId = answerId.value
        if (!currentAnswerId) {
            return
        }

        try {
            await submit((values) => {
                onSave(currentAnswerId, {...values})
            })
            close()
        } catch {
            return
        }
    }

    function remove(): void {
        const currentAnswerId = answerId.value
        if (!currentAnswerId) {
            return
        }
        onRemove(currentAnswerId)
        close()
    }

    return {
        open,
        answerId,
        form,
        errors,
        formError,
        submitting,
        show,
        close,
        save,
        remove,
    }
}
