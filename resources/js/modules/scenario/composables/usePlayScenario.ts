import {ref} from 'vue'
import {router} from '@inertiajs/vue3'
import {toast} from 'vue-sonner'
import {scenarioRunRepository} from '@/modules/scenario/repositories/scenarioRunRepository'

export function usePlayScenario() {
    const launching = ref(false)
    const launchError = ref<string | null>(null)

    async function launch(params: {
        scenarioId?: string | null
        versionId?: string | null
        context?: Record<string, unknown>
    }) {
        if (launching.value) return
        launching.value = true
        launchError.value = null
        try {
            const run = await scenarioRunRepository.create({
                scenario_id: params.scenarioId ?? null,
                scenario_version_id: params.versionId ?? null,
                context: params.context,
            })
            router.visit(route('surveys.run', run.id))
        } catch {
            launchError.value = 'Не удалось запустить сценарий. Обратитесь к администратору.'
            toast.error(launchError.value)
        } finally {
            launching.value = false
        }
    }

    return {launching, launchError, launch}
}
