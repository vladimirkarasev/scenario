import {ref} from 'vue'
import type {Ref} from 'vue'
import {roleRepository} from '@/modules/roles/repositories/roleRepository'
import type {PermissionOption, Role} from '@/modules/roles/types/role'
import {useFormToast} from '@/composables/useFormToast'
import {useZodForm} from '@/composables/useZodForm'
import {roleSchema} from '@/modules/roles/schemas/roleSchema'

export function useRoleModal(availablePermissions: Ref<PermissionOption[]>, onSaved: () => void) {
    const showModal = ref(false)
    const editing = ref<Role | null>(null)

    const {formData: form, errors, formError, submitting, submit, reset} =
        useZodForm(roleSchema, {name: '', title: '', description: '', permissions: [] as string[]})

    const formToast = useFormToast({
        created: 'Роль создана',
        updated: 'Роль обновлена',
        deleted: 'Роль удалена',
    })

    function openCreate(): void {
        editing.value = null
        reset({name: '', title: '', description: '', permissions: []})
        showModal.value = true
    }

    function openEdit(r: Role): void {
        editing.value = r
        reset({
            name: r.name,
            title: r.title ?? '',
            description: r.description ?? '',
            permissions: [...r.permissions],
        })
        showModal.value = true
    }

    function close(): void {
        showModal.value = false
        editing.value = null
    }

    async function save(): Promise<void> {
        const isUpdate = editing.value !== null
        try {
            await submit(async (data) => {
                const payload = {
                    name: data.name,
                    title: data.title || null,
                    description: data.description || null,
                    permissions: data.permissions,
                }
                if (editing.value) {
                    await roleRepository.update(editing.value.id, payload)
                } else {
                    await roleRepository.create(payload)
                }
            })
            close()
            onSaved()
            formToast.saved(isUpdate)
        } catch { /* errors уже в форме */
        }
    }

    // ── Delete confirm ───────────────────────────────────────────────────

    const confirmDelete = ref<Role | null>(null)
    const deleting = ref(false)
    const deleteError = ref<string | null>(null)

    function openDeleteConfirm(r: Role): void {
        confirmDelete.value = r
        deleteError.value = null
    }

    function closeDeleteConfirm(): void {
        confirmDelete.value = null
        deleteError.value = null
    }

    async function doDelete(): Promise<void> {
        if (!confirmDelete.value) return
        deleting.value = true
        deleteError.value = null
        try {
            await roleRepository.remove(confirmDelete.value.id)
            closeDeleteConfirm()
            onSaved()
            formToast.deleted()
        } catch (e: unknown) {
            deleteError.value = e instanceof Error ? e.message : 'Ошибка удаления.'
            formToast.error(e, 'Ошибка удаления.')
        } finally {
            deleting.value = false
        }
    }

    // ── Permission helpers ───────────────────────────────────────────────

    function togglePermission(name: string): void {
        const idx = form.permissions.indexOf(name)
        if (idx === -1) form.permissions.push(name)
        else form.permissions.splice(idx, 1)
    }

    function groupAllSelected(perms: PermissionOption[]): boolean {
        return perms.every(p => form.permissions.includes(p.name))
    }

    function togglePermGroup(perms: PermissionOption[]): void {
        if (groupAllSelected(perms)) {
            form.permissions = form.permissions.filter(n => !perms.some(p => p.name === n))
        } else {
            for (const p of perms) {
                if (!form.permissions.includes(p.name)) form.permissions.push(p.name)
            }
        }
    }

    function toggleAllPermissions(): void {
        if (form.permissions.length === availablePermissions.value.length) {
            form.permissions = []
        } else {
            form.permissions = availablePermissions.value.map(p => p.name)
        }
    }

    return {
        showModal, editing, form, errors, formError, submitting,
        openCreate, openEdit, close, save,
        confirmDelete, deleting, deleteError,
        openDeleteConfirm, closeDeleteConfirm, doDelete,
        togglePermission, groupAllSelected, togglePermGroup, toggleAllPermissions,
    }
}
