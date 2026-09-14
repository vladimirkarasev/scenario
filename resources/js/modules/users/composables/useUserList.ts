import {useUrlSearchParams} from '@vueuse/core'
import {computed, onBeforeUnmount, onMounted, ref, watch} from 'vue'
import {userRepository} from '@/modules/users/repositories/userRepository'
import type {User, UsersPage} from '@/modules/users/types/user'
import {useLatestRequest} from '@/composables/useLatestRequest'

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
    const users = ref<User[]>([])
    const meta = ref<UsersPage['meta']>({current_page: 1, last_page: 1, per_page: 20, total: 0})
    const {loading, error, execute, cancel} = useLatestRequest('Не удалось загрузить пользователей.')

    async function load(): Promise<void> {
        const qs = new URLSearchParams(window.location.search)
        if (!qs.has('page[number]')) qs.set('page[number]', '1')
        const result = await execute(() => userRepository.list(qs))
        if (!result) return
        users.value = result.data
        meta.value = result.meta
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
                params['page[number]'] = undefined
            } else {
                load()
            }
        }, 300)
    })
    watch(
        [
            () => params['page[number]'],
            () => toArr(params['filter[group_ids][]']).join('\u001f'),
            () => toArr(params['filter[role_ids][]']).join('\u001f'),
        ],
        load,
    )
    onMounted(load)
    onBeforeUnmount(() => {
        cancel()
        if (searchTimer) clearTimeout(searchTimer)
    })

    return {params, search, page, loading, error, users, meta, load}
}
