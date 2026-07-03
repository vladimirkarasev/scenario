import {onBeforeUnmount, onMounted, ref, watch} from 'vue'
import {groupRepository} from '@/modules/groups/repositories/groupRepository'
import type {Group, GroupsPage} from '@/modules/groups/types/group'

export function useGroupList() {
    const search = ref('')
    const page = ref(1)
    const loading = ref(false)
    const error = ref<string | null>(null)
    const groups = ref<Group[]>([])
    const meta = ref<GroupsPage['meta']>({current_page: 1, last_page: 1, per_page: 20, total: 0})

    let searchTimer: ReturnType<typeof setTimeout> | null = null
    let latestRequest = 0

    async function load(): Promise<void> {
        const request = ++latestRequest
        loading.value = true
        error.value = null
        try {
            // Размер страницы не хардкодим — дефолт задаёт backend (Pagination::DEFAULT_SIZE).
            const qs = new URLSearchParams({'page[number]': String(page.value)})
            if (search.value) qs.set('filter[search]', search.value)
            const result = await groupRepository.list(qs)
            if (request !== latestRequest) return
            groups.value = result.data
            meta.value = result.meta
        } catch (e: unknown) {
            if (request === latestRequest) {
                error.value = e instanceof Error ? e.message : 'Не удалось загрузить группы.'
            }
        } finally {
            if (request === latestRequest) loading.value = false
        }
    }

    onMounted(load)
    watch(page, load)
    watch(search, () => {
        if (searchTimer) clearTimeout(searchTimer)
        searchTimer = setTimeout(() => {
            if (page.value === 1) {
                load()
            } else {
                page.value = 1
            }
        }, 300)
    })
    onBeforeUnmount(() => {
        latestRequest++
        if (searchTimer) clearTimeout(searchTimer)
    })

    return {search, page, loading, error, groups, meta, load}
}
