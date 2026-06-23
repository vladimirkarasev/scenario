import {useUrlSearchParams} from '@vueuse/core'
import {computed, ref, watch} from 'vue'
import type {Ref} from 'vue'
import {
    directoryFeedRepository,
    type DirectoryFeedResponse,
    type DirectoryFeedRow,
} from '@/modules/directories/repositories/directoryFeedRepository'

const PAGE_SIZE = 20

type FeedParams = {
    'page[number]'?: string | string[]
    'filter[search]'?: string | string[]
}

function toArr(v: string | string[] | undefined): string[] {
    if (!v) return []
    return Array.isArray(v) ? v : [v]
}

/**
 * activeSection: 'all' | uuid — null означает uncategorized (root).
 */
export function useDirectoryFeed(activeSection: Ref<string | 'all'>) {
    const params = useUrlSearchParams<FeedParams>('history', {removeNullishValues: true})
    const loading = ref(false)
    const rows = ref<DirectoryFeedRow[]>([])
    const meta = ref<DirectoryFeedResponse['pagination']>({
        current_page: 1, last_page: 1, per_page: PAGE_SIZE, total: 0, folders_total: 0, items_total: 0,
    })

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
        loading.value = true
        try {
            const qs = new URLSearchParams()
            qs.set('page[number]', String(page.value))
            qs.set('page[size]', String(PAGE_SIZE))
            const searchTrim = search.value.trim()
            if (searchTrim) {
                // Глобальный поиск — parent_id не передаём
                qs.set('filter[search]', searchTrim)
            } else if (activeSection.value === 'all') {
                // Корень: uncategorized записи + root папки
                qs.set('filter[parent_id]', 'null')
            } else {
                qs.set('filter[parent_id]', activeSection.value)
            }
            const res = await directoryFeedRepository.fetch(qs)
            rows.value = res.data
            meta.value = res.pagination
        } catch { /* silent */
        } finally {
            loading.value = false
        }
    }

    watch([page, search, activeSection], () => {
        void load()
    }, {immediate: true})

    return {rows, meta, loading, page, search, load}
}
