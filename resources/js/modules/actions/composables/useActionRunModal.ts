import {ref} from 'vue'
import {toast} from 'vue-sonner'
import {actionRepository} from '@/modules/actions/repositories/actionRepository'
import type {Action} from '@/modules/actions/types/action'
import {useZodForm} from '@/composables/useZodForm'
import {actionRunSchema} from '@/modules/actions/schemas/actionRunSchema'

export function useActionRunModal(onRun: () => void) {
    const show = ref(false)
    const action = ref<Action | null>(null)

    const {formData: form, errors, formError, submitting, submit, reset} =
        useZodForm(actionRunSchema, {input: {source: 'manual'} as Record<string, unknown>})

    function open(item: Action): void {
        action.value = item
        reset({input: {source: 'manual'}})
        show.value = true
    }

    function close(): void {
        show.value = false
        action.value = null
    }

    async function run(): Promise<void> {
        if (!action.value) return
        const code = action.value.code || action.value.slug
        const actionId = action.value.id
        try {
            await submit(async (data) => {
                await actionRepository.run(code, actionId, data.input)
            })
            close()
            onRun()
            toast.success('Action запущен')
        } catch { /* errors уже в форме */
        }
    }

    return {show, saving: submitting, error: formError, errors, action, form, open, close, run}
}
