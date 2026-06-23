import {onMounted, ref, watch} from 'vue'
import {projectRepository} from '@/modules/projects/repositories/projectRepository'
import type {Project, ProjectsPage} from '@/modules/projects/types/project'

export function useProjectList() {
    const page = ref(1)
    const loading = ref(false)
    const projects = ref<Project[]>([])
    const meta = ref<ProjectsPage['meta']>({current_page: 1, last_page: 1, per_page: 20, total: 0})

    async function load(): Promise<void> {
        loading.value = true
        try {
            const qs = new URLSearchParams({'page[number]': String(page.value), 'page[size]': '20'})
            const result = await projectRepository.list(qs)
            projects.value = result.data
            meta.value = result.meta
        } catch { /* silent */
        } finally {
            loading.value = false
        }
    }

    onMounted(load)
    watch(page, load)

    return {page, loading, projects, meta, load}
}
