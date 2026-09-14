import {useUrlSearchParams} from '@vueuse/core'
import {computed, reactive, ref, watch} from 'vue'
import type {Ref} from 'vue'
import {useLatestRequest} from '@/composables/useLatestRequest'
import {scenarioFeedRepository} from '@/modules/scenario/repositories/scenarioFeedRepository'
import type {
    ScenarioFeedCounts,
    ScenarioFeedPagination,
    ScenarioFeedRow,
} from '@/modules/scenario/types/scenario-feed'

const PAGE_SIZE = 20

type FeedParams = {
    'page[number]'?: string | string[]
    'filter[search]'?: string | string[]
    'filter[status]'?: string | string[]
}

function toArr(v: string | string[] | undefined): string[] {
    if (!v) return []
    return Array.isArray(v) ? v : [v]
}

export type StatusTab = 'all' | 'active' | 'draft' | 'archived'

export function useScenarioFeed(
    activeFolder: Ref<string>,
    options: { excludeScenarioId?: Ref<string | null>; syncUrl?: boolean } = {},
) {
    const syncUrl = options.syncUrl ?? true
    const params: FeedParams = syncUrl
        ? useUrlSearchParams<FeedParams>('history', {removeNullishValues: true})
        : reactive<FeedParams>({})
    const rows = ref<ScenarioFeedRow[]>([])
    const meta = ref<ScenarioFeedPagination>({
        current_page: 1, last_page: 1, per_page: PAGE_SIZE, total: 0, from: null, to: null,
        folders_total: 0, items_total: 0,
    })
    const countsByStatus = ref<ScenarioFeedCounts>({
        all: 0, active: 0, draft: 0, archived: 0,
    })
    const {loading, error, execute} = useLatestRequest('Не удалось загрузить сценарии.')

    const page = computed({
        get: () => Number(toArr(params['page[number]'])[0]) || 1,
        set: (v: number) => {
            params['page[number]'] = v > 1 ? String(v) : undefined
        },
    })

    const search = computed({
        get: () => toArr(params['filter[search]'])[0] ?? '',
        set: (v: string) => {
            params['filter[search]'] = v || undefined
            params['page[number]'] = undefined
        },
    })

    const statusTab = computed<StatusTab>({
        get: () => {
            const raw = toArr(params['filter[status]'])[0]
            return (raw === 'active' || raw === 'draft' || raw === 'archived') ? raw : 'all'
        },
        set: (v: StatusTab) => {
            params['filter[status]'] = v !== 'all' ? v : undefined
            params['page[number]'] = undefined
        },
    })

    async function load(): Promise<void> {
        const qs = new URLSearchParams()
        qs.set('page[number]', String(page.value))
        qs.set('page[size]', String(PAGE_SIZE))
        const searchTrim = search.value.trim()
        if (searchTrim) {
            qs.set('filter[search]', searchTrim)
        } else if (activeFolder.value !== 'all') {
            qs.set('filter[parent_id]', activeFolder.value)
        }
        if (statusTab.value !== 'all') qs.set('filter[status]', statusTab.value)
        const exclude = options.excludeScenarioId?.value
        if (exclude) qs.set('filter[exclude_scenario_id]', exclude)
        const res = await execute(() => scenarioFeedRepository.fetch(qs))
        if (!res) return
        rows.value = res.data
        meta.value = {
            current_page: res.meta.current_page,
            last_page: res.meta.last_page,
            per_page: res.meta.per_page,
            total: res.meta.total,
            from: res.meta.from,
            to: res.meta.to,
            folders_total: res.meta.folders_total,
            items_total: res.meta.items_total,
        }
        countsByStatus.value = res.meta.counts_by_status
    }

    const watchSources: Array<Ref<unknown>> = [page, search, statusTab, activeFolder]
    if (options.excludeScenarioId) watchSources.push(options.excludeScenarioId)
    watch(watchSources, () => {
        void load()
    }, {immediate: true})

    return {rows, meta, countsByStatus, loading, error, page, search, statusTab, load}
}
