import {computed, reactive, ref} from 'vue'
import {defineStore} from 'pinia'
import {roleRepository} from '@/modules/roles/repositories/roleRepository'
import type {RoleManagerEndpoints} from '@/modules/roles/types/role'

interface Role {
    id: number
    name: string
}

export const useRoleManagerStore = defineStore('roleManager', () => {
    const items = ref<Role[]>([])
    const endpoints = ref<RoleManagerEndpoints | null>(null)
    const loading = ref(false)
    const error = ref('')
    const isDialogOpen = ref(false)
    const editingId = ref<number | null>(null)
    const form = reactive({name: ''})

    const dialogTitle = computed(() => editingId.value ? 'Edit role' : 'Create role')

    function initialize(nextItems: Role[], nextEndpoints: RoleManagerEndpoints): void {
        items.value = [...nextItems]
        endpoints.value = nextEndpoints
    }

    function resetForm(): void {
        form.name = ''
        editingId.value = null
    }

    function openCreateDialog(): void {
        resetForm()
        error.value = ''
        isDialogOpen.value = true
    }

    function openEditDialog(item: Role): void {
        form.name = item.name
        editingId.value = item.id
        error.value = ''
        isDialogOpen.value = true
    }

    async function submit(): Promise<void> {
        loading.value = true
        error.value = ''

        try {
            const isUpdate = editingId.value !== null
            const item = await roleRepository.saveAt(
                isUpdate ? `${endpoints.value!.update}/${editingId.value}` : endpoints.value!.store,
                form.name,
                isUpdate,
            )

            items.value = editingId.value
                ? items.value
                    .map((role) => role.id === item.id ? item : role)
                    .sort((left, right) => left.name.localeCompare(right.name))
                : [...items.value, item].sort((left, right) => left.name.localeCompare(right.name))

            isDialogOpen.value = false
            resetForm()
        } catch (e: unknown) {
            error.value = e instanceof Error ? e.message : String(e)
        } finally {
            loading.value = false
        }
    }

    async function remove(item: Role): Promise<void> {
        if (!window.confirm(`Delete role "${item.name}"?`)) {
            return
        }

        loading.value = true
        error.value = ''

        try {
            await roleRepository.removeAt(`${endpoints.value!.destroy}/${item.id}`)
            items.value = items.value.filter((role) => role.id !== item.id)
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
