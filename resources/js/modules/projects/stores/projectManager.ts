import {computed, reactive, ref} from 'vue'
import {defineStore} from 'pinia'
import {destroyJson, sendJson} from '@/lib/http'

interface Project {
    id: string
    name: string
    sitekey: string
    host: string
    shared_secret: string
    is_active: boolean
}

interface ProjectEndpoints {
    store: string
    update: string
    destroy: string
}

export const useProjectManagerStore = defineStore('projectManager', () => {
    const items = ref<Project[]>([])
    const endpoints = ref<ProjectEndpoints | null>(null)
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

    function initialize(nextItems: Project[], nextEndpoints: ProjectEndpoints): void {
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

    function openEditDialog(item: Project): void {
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
            const payload = await sendJson<Record<string, unknown>>(
                editingId.value
                    ? `${endpoints.value!.update}/${editingId.value}`
                    : endpoints.value!.store,
                {
                    method: editingId.value ? 'PUT' : 'POST',
                    body: {...form},
                    fallbackMessage: 'Failed to save project.',
                },
            )

            const item = payload.item as Project

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

    async function remove(item: Project): Promise<void> {
        if (!window.confirm(`Delete project "${item.name}"?`)) {
            return
        }

        loading.value = true
        error.value = ''

        try {
            await destroyJson(`${endpoints.value!.destroy}/${item.id}`, 'Failed to delete project.')
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
