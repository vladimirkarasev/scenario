import {useUrlSearchParams} from '@vueuse/core'
import {computed, onBeforeUnmount, onMounted, ref, watch} from 'vue'
import {userRepository} from '@/modules/users/repositories/userRepository'
import type {User, UsersPage} from '@/modules/users/types/user'

export type UserListParams = {
    'filter[search]'?: string | string[]
    'page[number]'?: string | string[]
    'filter[group_ids][]'?: string | string[]
    'filter[role_ids][]'?: string | string[]
}

function toArr(v: string | string[] | undefined): string[] {
    if (!v) return []
    return Array.isArray(v) ? v : [v]
}

export function useUserList() {
    const params = useUrlSearchParams<UserListParams>('history', {removeNullishValues: true})
    const loading = ref(false)
    const error = ref<string | null>(null)
    const users = ref<User[]>([])
    const meta = ref<UsersPage['meta']>({current_page: 1, last_page: 1, per_page: 15, total: 0})
    let requestId = 0

    async function load(): Promise<void> {
        const currentRequestId = ++requestId
        loading.value = true
        error.value = null
        try {
            const qs = new URLSearchParams(window.location.search)
            qs.set('per_page', '15')
            if (!qs.has('page[number]')) qs.set('page[number]', '1')
            const result = await userRepository.list(qs)
            if (currentRequestId === requestId) {
                users.value = result.data
                meta.value = result.meta
            }
        } catch (e: unknown) {
            if (currentRequestId === requestId) {
                error.value = e instanceof Error ? e.message : 'Не удалось загрузить пользователей.'
            }
        } finally {
            if (currentRequestId === requestId) loading.value = false
        }
    }

    const search = computed({
        get: () => toArr(params['filter[search]'])[0] ?? '',
        set: (v: string) => {
            params['filter[search]'] = v || undefined
            params['page[number]'] = undefined
        },
    })

    const page = computed({
        get: () => Number(toArr(params['page[number]'])[0]) || 1,
        set: (v: number) => {
            params['page[number]'] = v > 1 ? String(v) : undefined
        },
    })

    let searchTimer: ReturnType<typeof setTimeout> | null = null
    watch(() => params['filter[search]'], () => {
        if (searchTimer) clearTimeout(searchTimer)
        searchTimer = setTimeout(() => {
            if (params['page[number]'] !== undefined) {
                params['page[number]'] = undefined  // triggers page watcher → load()
            } else {
                load()
            }
        }, 300)
    })
    watch(
        [() => params['page[number]'], () => params['filter[group_ids][]'], () => params['filter[role_ids][]']],
        load,
        {deep: true},
    )
    onMounted(load)
    onBeforeUnmount(() => {
        requestId++
        if (searchTimer) clearTimeout(searchTimer)
    })

    return {params, search, page, loading, error, users, meta, load}
}
