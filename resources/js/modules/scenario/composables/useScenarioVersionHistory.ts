import {onMounted, ref, watch} from 'vue'
import {useLatestRequest} from '@/composables/useLatestRequest'
import {scenarioRepository} from '@/modules/scenario/repositories/scenarioRepository'
import {scenarioVersionRepository} from '@/modules/scenario/repositories/scenarioVersionRepository'
import type {ScenarioVersionRevision} from '@/modules/scenario/types/scenario-version'
import type {PaginationMeta} from '@/types/pagination'

const EMPTY_META: PaginationMeta = {current_page: 1, last_page: 1, per_page: 20, total: 0}

export function useScenarioVersionHistory(scenarioId: string, versionId: string) {
    const scenarioName = ref('')
    const versionName = ref('')
    const page = ref(1)
    const revisions = ref<ScenarioVersionRevision[]>([])
    const meta = ref<PaginationMeta>({...EMPTY_META})
    const {loading, error, execute} = useLatestRequest('Не удалось загрузить историю версии.')

    async function loadHeader(): Promise<void> {
        const result = await execute(async () => await Promise.all([
            scenarioRepository.find(scenarioId),
            scenarioVersionRepository.settings(scenarioId, versionId),
        ]))
        if (!result) return
        scenarioName.value = result[0].name
        versionName.value = result[1].name ?? ''
    }

    async function loadHistory(): Promise<void> {
        const result = await execute(() => scenarioVersionRepository.history(scenarioId, versionId, page.value))
        if (!result) return
        revisions.value = result.data
        meta.value = result.meta
    }

    onMounted(async () => {
        await loadHeader()
        await loadHistory()
    })
    watch(page, loadHistory)

    return {scenarioName, versionName, page, revisions, meta, loading, error, loadHistory}
}
