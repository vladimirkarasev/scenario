import {onMounted, ref, watch} from 'vue'
import {groupRepository} from '@/modules/groups/repositories/groupRepository'
import type {Group, GroupsPage} from '@/modules/groups/types/group'

export function useGroupList() {
    const search = ref('')
    const page = ref(1)
    const loading = ref(false)
    const groups = ref<Group[]>([])
    const meta = ref<GroupsPage['meta']>({current_page: 1, last_page: 1, per_page: 20, total: 0})

    let searchTimer: ReturnType<typeof setTimeout> | null = null

    async function load(): Promise<void> {
        loading.value = true
        try {
            // Размер страницы не хардкодим — дефолт задаёт backend (Pagination::DEFAULT_SIZE).
            const qs = new URLSearchParams({'page[number]': String(page.value)})
            if (search.value) qs.set('filter[search]', search.value)
            const result = await groupRepository.list(qs)
            groups.value = result.data
            meta.value = result.meta
        } catch { /* silent */
        } finally {
            loading.value = false
        }
    }

    onMounted(load)
    watch(page, load)
    watch(search, () => {
        if (searchTimer) clearTimeout(searchTimer)
        searchTimer = setTimeout(() => {
            page.value = 1;
            load()
        }, 300)
    })

    return {search, page, loading, groups, meta, load}
}
