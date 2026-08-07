import {useUrlSearchParams} from '@vueuse/core'
import {computed, ref, watch} from 'vue'
import type {Ref} from 'vue'
import {useLatestRequest} from '@/composables/useLatestRequest'
import {actionFeedRepository} from '@/modules/actions/repositories/actionFeedRepository'
import type {ActionFeedMeta, ActionFeedRow} from '@/modules/actions/types/feed'

const PAGE_SIZE = 20

type FeedParams = {
    'page[number]'?: string | string[]
    'filter[search]'?: string | string[]
}

function toArr(v: string | string[] | undefined): string[] {
    if (!v) return []
    return Array.isArray(v) ? v : [v]
}

export function useActionFeed(activeSection: Ref<string | 'all'>) {
    const params = useUrlSearchParams<FeedParams>('history', {removeNullishValues: true})
    const rows = ref<ActionFeedRow[]>([])
    const meta = ref<ActionFeedMeta>({
        current_page: 1, last_page: 1, per_page: PAGE_SIZE, total: 0, folders_total: 0, items_total: 0,
    })
    const {loading, error, execute} = useLatestRequest('Не удалось загрузить действия.')

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

    async function load(): Promise<void> {
        const qs = new URLSearchParams()
        qs.set('page[number]', String(page.value))
        qs.set('page[size]', String(PAGE_SIZE))
        const searchTrim = search.value.trim()
        if (searchTrim) {
            qs.set('filter[search]', searchTrim)
        } else if (activeSection.value === 'all') {
            qs.set('filter[parent_id]', 'null')
        } else {
            qs.set('filter[parent_id]', activeSection.value)
        }
        const res = await execute(() => actionFeedRepository.fetch(qs))
        if (!res) return
        rows.value = res.data
        meta.value = res.pagination
    }

    watch([page, search, activeSection], () => {
        void load()
    }, {immediate: true})

    return {rows, meta, loading, error, page, search, load}
}
