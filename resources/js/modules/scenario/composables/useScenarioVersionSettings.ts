import {onMounted, ref} from 'vue'
import {useFormToast} from '@/composables/useFormToast'
import {useLatestRequest} from '@/composables/useLatestRequest'
import {useZodForm} from '@/composables/useZodForm'
import {scenarioVersionRepository} from '@/modules/scenario/repositories/scenarioVersionRepository'
import {scenarioRepository} from '@/modules/scenario/repositories/scenarioRepository'
import {scenarioVersionSchema} from '@/modules/scenario/schemas/scenarioSchema'

export function useScenarioVersionSettings(scenarioId: string, versionId: string) {
    const scenarioName = ref('')
    const createdAt = ref<string | null>(null)
    const updatedAt = ref<string | null>(null)
    const {loading, error: loadingError, execute} = useLatestRequest('Не удалось загрузить настройки версии.')
    const {formData: form, errors, formError, submitting, submit, reset} = useZodForm(
        scenarioVersionSchema,
        {name: '', status: 'draft' as const},
    )
    const formToast = useFormToast({created: 'Версия создана', updated: 'Настройки версии сохранены'})

    async function load(): Promise<void> {
        const result = await execute(async () => await Promise.all([
            scenarioRepository.find(scenarioId),
            scenarioVersionRepository.settings(scenarioId, versionId),
        ]))
        if (!result) return
        const [scenario, version] = result
        scenarioName.value = scenario.name
        createdAt.value = version.created_at
        updatedAt.value = version.updated_at
        reset({name: version.name ?? '', status: version.status})
    }

    async function save(): Promise<void> {
        try {
            await submit(async (data) => {
                const updated = await scenarioVersionRepository.updateSettings(scenarioId, versionId, {
                    name: data.name || null,
                    status: data.status,
                })
                updatedAt.value = updated.updated_at
                reset({name: updated.name ?? '', status: updated.status})
            })
            formToast.saved(true)
        } catch {
            return
        }
    }

    onMounted(load)

    return {
        scenarioName,
        createdAt,
        updatedAt,
        loading,
        loadingError,
        form,
        errors,
        formError,
        submitting,
        save,
    }
}
