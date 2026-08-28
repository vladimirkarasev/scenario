import {ref} from 'vue'
import {scenarioRepository} from '@/modules/scenario/repositories/scenarioRepository'
import type {Scenario} from '@/modules/scenario/types/scenario'
import {useFormToast} from '@/composables/useFormToast'
import {useZodForm} from '@/composables/useZodForm'
import {scenarioCreateSchema} from '@/modules/scenario/schemas/scenarioCreateSchema'

const initial = () => ({
    name: '',
    type: 'colls' as const,
    description: '',
    alias: '',
    tags: '',
    category_ids: [] as string[],
})

export function useScenarioCreateModal(onCreated: (scenario: Scenario) => void) {
    const showModal = ref(false)

    const {formData: form, errors, formError, submitting, submit, reset} =
        useZodForm(scenarioCreateSchema, initial())

    const formToast = useFormToast({
        created: 'Сценарий создан',
        updated: 'Сценарий обновлён',
    })

    function openCreate(presetCategoryIds: string[] = []): void {
        reset({...initial(), category_ids: [...presetCategoryIds]})
        showModal.value = true
    }

    function close(): void {
        showModal.value = false
    }

    async function save(): Promise<void> {
        try {
            let created: Scenario | null = null
            await submit(async (data) => {
                const tags = data.tags.split(/[\n,]/).map(s => s.trim()).filter(Boolean)
                created = await scenarioRepository.create({
                    name: data.name,
                    type: data.type,
                    description: data.description || null,
                    alias: data.alias || null,
                    tags,
                    is_active: true,
                    category_ids: data.category_ids,
                })
            })
            close()
            if (created) {
                onCreated(created)
                formToast.saved(false)
            }
        } catch { /* errors уже в форме */
        }
    }

    return {
        showModal,
        form, errors, formError, submitting,
        openCreate, close, save,
    }
}
