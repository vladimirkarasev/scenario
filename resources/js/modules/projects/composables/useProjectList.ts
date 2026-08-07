import {onBeforeUnmount, onMounted, ref, watch} from 'vue'
import {useLatestRequest} from '@/composables/useLatestRequest'
import {projectRepository} from '@/modules/projects/repositories/projectRepository'
import type {Project, ProjectsPage} from '@/modules/projects/types/project'

export function useProjectList() {
    const page = ref(1)
    const search = ref('')
    const projects = ref<Project[]>([])
    const meta = ref<ProjectsPage['meta']>({current_page: 1, last_page: 1, per_page: 20, total: 0})
    const {loading, error, execute} = useLatestRequest('Не удалось загрузить проекты.')

    async function load(): Promise<void> {
        const qs = new URLSearchParams({'page[number]': String(page.value), 'page[size]': '20'})
        if (search.value.trim()) qs.set('filter[search]', search.value.trim())
        const result = await execute(() => projectRepository.list(qs))
        if (!result) return
        projects.value = result.data
        meta.value = result.meta
    }

    onMounted(load)
    watch(page, load)
    let searchTimer: ReturnType<typeof setTimeout> | null = null
    watch(search, () => {
        if (searchTimer) clearTimeout(searchTimer)
        searchTimer = setTimeout(() => {
            if (page.value === 1) {
                void load()
            } else {
                page.value = 1
            }
        }, 300)
    })
    onBeforeUnmount(() => {
        if (searchTimer) clearTimeout(searchTimer)
    })

    return {page, search, loading, error, projects, meta, load}
}
