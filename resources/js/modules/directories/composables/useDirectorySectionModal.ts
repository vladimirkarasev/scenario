import { computed, ref } from 'vue'
import { categoryRepository, type CategoryRef } from '@/modules/scenario/repositories/categoryRepository'
import { useFormToast } from '@/composables/useFormToast'
import { useZodForm } from '@/composables/useZodForm'
import { directorySectionSchema } from '@/modules/directories/schemas/directorySectionSchema'

export function useDirectorySectionModal(
    onCreated: (category: CategoryRef) => void,
    onUpdated: (category: CategoryRef) => void,
) {
    const open      = ref(false)
    const editingId = ref<string | null>(null)

    const { formData, errors, formError, submitting, submit, reset } =
        useZodForm(directorySectionSchema, { name: '', parent_id: null })

    const isEditing = computed(() => editingId.value !== null)

    const formToast = useFormToast({
        created: 'Раздел создан',
        updated: 'Раздел обновлён',
    })

    function openModal(pId: string | null = null): void {
        editingId.value = null
        reset({ name: '', parent_id: pId })
        open.value = true
    }

    function openEdit(category: CategoryRef): void {
        editingId.value = category.id
        reset({ name: category.name, parent_id: category.parent_id })
        open.value = true
    }

    function close(): void {
        open.value = false
        editingId.value = null
    }

    async function submitForm(): Promise<void> {
        try {
            await submit(async (data) => {
                const payload = { name: data.name.trim(), parent_id: data.parent_id, is_active: true }
                if (editingId.value) {
                    const updated = await categoryRepository.update(editingId.value, payload)
                    onUpdated(updated)
                    formToast.saved(true)
                } else {
                    const created = await categoryRepository.create(payload)
                    onCreated(created)
                    formToast.saved(false)
                }
            })
            close()
        } catch { /* ошибка отображена в форме */ }
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
