import {useUrlSearchParams} from '@vueuse/core'
import {computed, getCurrentScope, onMounted, onScopeDispose, ref, watch} from 'vue'
import type {Ref} from 'vue'
import {useLatestRequest} from '@/composables/useLatestRequest'
import {directoryRepository} from '@/modules/directories/repositories/directoryRepository'
import type {Directory, DirectoryListMeta} from '@/modules/directories/types/directory'

const PAGE_SIZE = 20

export type DirectoryListParams = {
    'page[number]'?: string | string[]
    'filter[search]'?: string | string[]
}

function toArr(v: string | string[] | undefined): string[] {
    if (!v) return []
    return Array.isArray(v) ? v : [v]
}

export function useDirectoryList(categoryIds: Ref<string[]>, rootOnly: Ref<boolean>) {
    const params = useUrlSearchParams<DirectoryListParams>('history', {removeNullishValues: true})
    const directories = ref<Directory[]>([])
    const meta = ref<DirectoryListMeta>({current_page: 1, last_page: 1, per_page: PAGE_SIZE, total: 0})
    const {loading, error, execute} = useLatestRequest('Не удалось загрузить справочники.')

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
        if (search.value) qs.set('filter[search]', search.value)
        if (rootOnly.value) qs.set('filter[uncategorized]', 'true')
        categoryIds.value.forEach(id => qs.append('filter[category_ids][]', id))
        const result = await execute(() => directoryRepository.list(qs))
        if (!result) return
        directories.value = result.items
        meta.value = result.meta
    }

    async function removeDirectory(id: string): Promise<void> {
        await directoryRepository.remove(id)
        directories.value = directories.value.filter(d => String(d.id) !== String(id))
        meta.value.total = Math.max(0, meta.value.total - 1)
    }

    watch([() => categoryIds.value.join('\u001f'), rootOnly], () => {
        params['page[number]'] = undefined
        void load()
    })

    watch(() => params['page[number]'], load)

    let searchTimer: ReturnType<typeof setTimeout> | null = null
    watch(() => params['filter[search]'], () => {
        if (searchTimer) clearTimeout(searchTimer)
        searchTimer = setTimeout(() => {
            if (params['page[number]'] !== undefined) {
                params['page[number]'] = undefined
            } else {
                void load()
            }
        }, 300)
    })

    onMounted(load)
    if (getCurrentScope()) {
        onScopeDispose(() => {
            if (searchTimer) clearTimeout(searchTimer)
        })
    }

    return {directories, loading, error, meta, page, search, load, removeDirectory}
}
