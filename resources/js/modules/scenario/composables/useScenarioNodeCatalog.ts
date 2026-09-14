import {inject, provide, ref, watch, type InjectionKey, type Ref} from 'vue'
import {scenarioNodeCatalogRepository} from '@/modules/scenario/repositories/scenarioNodeCatalogRepository'
import type {ScenarioType} from '@/modules/scenario/types/scenario'
import type {ScenarioNodeDefinition} from '@/modules/scenario/types/scenario-node-catalog'

interface ScenarioNodeCatalogContext {
    nodes: Ref<ScenarioNodeDefinition[]>
    loading: Ref<boolean>
    error: Ref<string | null>
}

const scenarioNodeCatalogKey: InjectionKey<ScenarioNodeCatalogContext> = Symbol('scenario-node-catalog')

function createScenarioNodeCatalog(type: () => ScenarioType): ScenarioNodeCatalogContext {
    const nodes = ref<ScenarioNodeDefinition[]>([])
    const loading = ref(false)
    const error = ref<string | null>(null)
    let requestNumber = 0

    watch(type, async (scenarioType) => {
        const currentRequest = ++requestNumber
        loading.value = true
        error.value = null

        try {
            const catalog = await scenarioNodeCatalogRepository.get(scenarioType)
            if (currentRequest === requestNumber) {
                nodes.value = catalog.nodes
            }
        } catch (reason) {
            if (currentRequest === requestNumber) {
                nodes.value = []
                error.value = reason instanceof Error ? reason.message : 'Не удалось загрузить доступные узлы.'
            }
        } finally {
            if (currentRequest === requestNumber) {
                loading.value = false
            }
        }
    }, {immediate: true})

    return {nodes, loading, error}
}

export function provideScenarioNodeCatalog(type: () => ScenarioType): ScenarioNodeCatalogContext {
    const context = createScenarioNodeCatalog(type)
    provide(scenarioNodeCatalogKey, context)

    return context
}

export function useScenarioNodeCatalog(): ScenarioNodeCatalogContext {
    return inject(scenarioNodeCatalogKey)
        ?? createScenarioNodeCatalog(() => 'colls')
}
