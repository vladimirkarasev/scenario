import {computed, ref} from 'vue'
import {scenarioCategoryRepository} from '@/modules/scenario/repositories/scenarioCategoryRepository'
import type {CategoryRef} from '@/modules/scenario/repositories/categoryRepository'
import {useFormToast} from '@/composables/useFormToast'
import {useZodForm} from '@/composables/useZodForm'
import {scenarioSectionSchema} from '@/modules/scenario/schemas/scenarioSectionSchema'

export function useScenarioSectionModal(
    onCreated: (category: CategoryRef) => void,
    onUpdated: (category: CategoryRef) => void,
) {
    const open = ref(false)
    const editingId = ref<string | null>(null)
    const selectedGroups = ref<Array<Record<string, unknown>>>([])

    const {formData, errors, formError, submitting, submit, reset} =
        useZodForm(scenarioSectionSchema, {
            name: '',
            parent_id: null as string | null,
            group_ids: [] as string[],
            inherit_to_descendants: false,
            is_workspace: false,
        })

    const isEditing = computed(() => editingId.value !== null)

    const formToast = useFormToast({
        created: 'Раздел создан',
        updated: 'Раздел обновлён',
    })

    function openModal(pId: string | null = null): void {
        editingId.value = null
        selectedGroups.value = []
        reset({
            name: '',
            parent_id: pId,
            group_ids: [],
            inherit_to_descendants: false,
            is_workspace: false,
        })
        open.value = true
    }

    function openEdit(category: CategoryRef): void {
        editingId.value = category.id
        const groupIds = category.group_ids ?? []
        // selectedGroups получает минимальный набор; реальные имена подтянутся при поиске.
        selectedGroups.value = groupIds.map(id => ({id, name: id}))
        reset({
            name: category.name,
            parent_id: category.parent_id,
            group_ids: groupIds,
            inherit_to_descendants: false,
            is_workspace: category.is_workspace ?? false,
        })
        open.value = true
    }

    function close(): void {
        open.value = false
        editingId.value = null
    }

    async function submitForm(): Promise<void> {
        try {
            await submit(async (data) => {
                const groupIds = selectedGroups.value.map(g => String(g.id))
                const payload = {
                    name: data.name.trim(),
                    parent_id: data.parent_id,
                    is_active: true,
                    group_ids: groupIds,
                    inherit_to_descendants: data.inherit_to_descendants,
                    is_workspace: data.is_workspace,
                }
                if (editingId.value) {
                    const updated = await scenarioCategoryRepository.update(editingId.value, payload)
                    onUpdated(updated)
                    formToast.saved(true)
                } else {
                    const created = await scenarioCategoryRepository.create(payload)
                    onCreated(created)
                    formToast.saved(false)
                }
            })
            close()
        } catch { /* ошибка отображена в форме */
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
        selectedGroups,
        openModal,
        openEdit,
        close,
        submit: submitForm,
    }
}
