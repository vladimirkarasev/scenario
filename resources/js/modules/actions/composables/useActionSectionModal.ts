import {computed, ref} from 'vue'
import {actionCategoryRepository} from '@/modules/actions/repositories/actionCategoryRepository'
import type {ActionCategory} from '@/modules/actions/types/action'
import {useFormToast} from '@/composables/useFormToast'
import {useZodForm} from '@/composables/useZodForm'
import {actionSectionSchema} from '@/modules/actions/schemas/actionSectionSchema'

export function useActionSectionModal(
    onCreated: (category: ActionCategory) => void,
    onUpdated: (category: ActionCategory) => void,
) {
    const open = ref(false)
    const editingId = ref<string | null>(null)

    const {formData, errors, formError, submitting, submit, reset} =
        useZodForm(actionSectionSchema, {
            name: '',
            parent_id: null as string | null,
        })

    const isEditing = computed(() => editingId.value !== null)

    const formToast = useFormToast({
        created: 'Раздел создан',
        updated: 'Раздел обновлён',
    })

    function openModal(parentId: string | null = null): void {
        editingId.value = null
        reset({name: '', parent_id: parentId})
        open.value = true
    }

    function openEdit(category: ActionCategory): void {
        editingId.value = category.id
        reset({name: category.name, parent_id: category.parent_id})
        open.value = true
    }

    function close(): void {
        open.value = false
        editingId.value = null
    }

    async function submitForm(): Promise<void> {
        try {
            await submit(async (data) => {
                const payload = {
                    name: data.name.trim(),
                    parent_id: data.parent_id,
                    is_active: true,
                }
                if (editingId.value) {
                    onUpdated(await actionCategoryRepository.update(editingId.value, payload))
                    formToast.saved(true)
                } else {
                    onCreated(await actionCategoryRepository.create(payload))
                    formToast.saved(false)
                }
            })
            close()
        } catch { /* ошибка в форме */
        }
    }

    return {
        open,
        editingId,
        isEditing,
        form: formData,
        errors,
        formError,
        submitting,
        openModal,
        openEdit,
        close,
        submit: submitForm,
    }
}
