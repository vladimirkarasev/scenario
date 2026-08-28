import {inject, provide, ref, watch, type InjectionKey, type Ref} from 'vue'
import {scenarioSystemVariableRepository} from '@/modules/scenario/repositories/scenarioSystemVariableRepository'
import type {ScenarioType} from '@/modules/scenario/types/scenario'
import type {SystemVariableGroup} from '@/modules/scenario/types/scenario-system-variable'

interface ScenarioSystemVariablesContext {
    groups: Ref<SystemVariableGroup[]>
    loading: Ref<boolean>
    error: Ref<string | null>
}

const scenarioSystemVariablesKey: InjectionKey<ScenarioSystemVariablesContext> = Symbol('scenario-system-variables')

function createScenarioSystemVariables(type: () => ScenarioType): ScenarioSystemVariablesContext {
    const groups = ref<SystemVariableGroup[]>([])
    const loading = ref(false)
    const error = ref<string | null>(null)
    let requestNumber = 0

    watch(type, async (scenarioType) => {
        const currentRequest = ++requestNumber
        loading.value = true
        error.value = null

        try {
            const catalog = await scenarioSystemVariableRepository.get(scenarioType)
            if (currentRequest === requestNumber) {
                groups.value = catalog.groups
            }
        } catch (reason) {
            if (currentRequest === requestNumber) {
                groups.value = []
                error.value = reason instanceof Error ? reason.message : 'Не удалось загрузить системные переменные.'
            }
        } finally {
            if (currentRequest === requestNumber) {
                loading.value = false
            }
        }
    }, {immediate: true})

    return {groups, loading, error}
}

export function provideScenarioSystemVariables(type: () => ScenarioType): ScenarioSystemVariablesContext {
    const context = createScenarioSystemVariables(type)
    provide(scenarioSystemVariablesKey, context)

    return context
}

export function useScenarioSystemVariables(): ScenarioSystemVariablesContext {
    return inject(scenarioSystemVariablesKey)
        ?? createScenarioSystemVariables(() => 'colls')
}
