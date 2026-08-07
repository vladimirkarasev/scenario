import {computed, reactive, ref} from 'vue'
import {defineStore} from 'pinia'
import {projectRepository} from '@/modules/projects/repositories/projectRepository'
import type {ProjectManagerEndpoints, ProjectManagerItem} from '@/modules/projects/types/project'

export const useProjectManagerStore = defineStore('projectManager', () => {
    const items = ref<ProjectManagerItem[]>([])
    const endpoints = ref<ProjectManagerEndpoints | null>(null)
    const loading = ref(false)
    const error = ref('')
    const isDialogOpen = ref(false)
    const editingId = ref<string | null>(null)
    const form = reactive({
        name: '',
        sitekey: '',
        host: '',
        shared_secret: '',
        is_active: true,
    })

    const dialogTitle = computed(() => editingId.value ? 'Edit project' : 'Create project')

    function initialize(nextItems: ProjectManagerItem[], nextEndpoints: ProjectManagerEndpoints): void {
        items.value = [...nextItems]
        endpoints.value = nextEndpoints
    }

    function resetForm(): void {
        form.name = ''
        form.sitekey = ''
        form.host = ''
        form.shared_secret = ''
        form.is_active = true
        editingId.value = null
    }

    function openCreateDialog(): void {
        resetForm()
        error.value = ''
        isDialogOpen.value = true
    }

    function openEditDialog(item: ProjectManagerItem): void {
        form.name = item.name
        form.sitekey = item.sitekey
        form.host = item.host
        form.shared_secret = item.shared_secret
        form.is_active = Boolean(item.is_active)
        editingId.value = item.id
        error.value = ''
        isDialogOpen.value = true
    }

    async function submit(): Promise<void> {
        loading.value = true
        error.value = ''

        try {
            const isUpdate = editingId.value !== null
            const item = await projectRepository.saveAt(
                isUpdate ? `${endpoints.value!.update}/${editingId.value}` : endpoints.value!.store,
                {...form},
                isUpdate,
            )

            items.value = editingId.value
                ? items.value.map((project) => project.id === item.id ? item : project)
                : [...items.value, item].sort((left, right) => left.name.localeCompare(right.name))

            isDialogOpen.value = false
            resetForm()
        } catch (e: unknown) {
            error.value = e instanceof Error ? e.message : String(e)
        } finally {
            loading.value = false
        }
    }

    async function remove(item: ProjectManagerItem): Promise<void> {
        if (!window.confirm(`Delete project "${item.name}"?`)) {
            return
        }

        loading.value = true
        error.value = ''

        try {
            await projectRepository.removeAt(`${endpoints.value!.destroy}/${item.id}`)
            items.value = items.value.filter((project) => project.id !== item.id)
        } catch (e: unknown) {
            error.value = e instanceof Error ? e.message : String(e)
        } finally {
            loading.value = false
        }
    }

    return {
        items,
        loading,
        error,
        isDialogOpen,
        editingId,
        form,
        dialogTitle,
        initialize,
        openCreateDialog,
        openEditDialog,
        submit,
        remove,
    }
})
