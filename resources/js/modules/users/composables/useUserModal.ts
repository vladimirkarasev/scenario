import { ref, shallowRef } from 'vue'
import { userRepository } from '@/modules/users/repositories/userRepository'
import { roleRepository } from '@/modules/roles/repositories/roleRepository'
import { groupRepository } from '@/modules/groups/repositories/groupRepository'
import type { User } from '@/modules/users/types/user'
import { useFormToast } from '@/composables/useFormToast'
import { useZodForm } from '@/composables/useZodForm'
import { userSchema } from '@/modules/users/schemas/userSchema'

type RoleItem  = { id: string; name: string; title: string | null }
type GroupItem = { id: string; name: string }

const emptyForm = () => ({
  name: '', fio: '', email: '', login: '', external_id: '', password: '',
  roles: [] as string[], group_ids: [] as string[],
})

export function useUserModal(onSaved: () => void) {
  const showModal    = ref(false)
  const editing      = ref<User | null>(null)
  const modalLoading = ref(false)

  const schemaRef = shallowRef(userSchema(false))

  const { formData: form, errors, formError, submitting, submit, reset } =
    useZodForm(schemaRef.value, emptyForm())

  const formToast = useFormToast({
    created: 'Пользователь создан',
    updated: 'Пользователь обновлён',
    deleted: 'Пользователь удалён',
  })

  // ── Selected items mirror form.roles / form.group_ids ───────────────

  const selectedRoles  = ref<RoleItem[]>([])
  const selectedGroups = ref<GroupItem[]>([])

  // ── Role search ──────────────────────────────────────────────────────

  const roleSearch       = ref('')
  const roleDropdownOpen = ref(false)
  const roleResults      = ref<RoleItem[]>([])
  let roleTimer: ReturnType<typeof setTimeout> | null = null

  function onRoleInput(): void {
    roleDropdownOpen.value = false
    if (roleTimer) clearTimeout(roleTimer)
    if (!roleSearch.value.trim()) { roleResults.value = []; return }
    roleTimer = setTimeout(async () => {
      try {
        const res = await roleRepository.list(new URLSearchParams({ 'page[size]': '20', 'filter[search]': roleSearch.value }))
        roleResults.value = res
          .filter(r => !form.roles.includes(r.name))
          .map(r => ({ id: String(r.id), name: r.name, title: r.title }))
        roleDropdownOpen.value = roleResults.value.length > 0
      } catch { /* silent */ }
    }, 250)
  }

  function addRole(r: RoleItem): void {
    selectedRoles.value.push(r)
    form.roles.push(r.name)
    roleSearch.value = ''
    roleResults.value = []
    roleDropdownOpen.value = false
  }

  function removeRole(name: string): void {
    selectedRoles.value = selectedRoles.value.filter(r => r.name !== name)
    form.roles = form.roles.filter(n => n !== name)
  }

  // ── Group search ─────────────────────────────────────────────────────

  const groupSearch       = ref('')
  const groupDropdownOpen = ref(false)
  const groupResults      = ref<GroupItem[]>([])
  let groupTimer: ReturnType<typeof setTimeout> | null = null

  function onGroupInput(): void {
    groupDropdownOpen.value = false
    if (groupTimer) clearTimeout(groupTimer)
    if (!groupSearch.value.trim()) { groupResults.value = []; return }
    groupTimer = setTimeout(async () => {
      try {
        const res = await groupRepository.list(new URLSearchParams({ 'page[size]': '15', 'filter[search]': groupSearch.value }))
        groupResults.value = res.data
          .filter(g => !form.group_ids.includes(g.id))
          .map(g => ({ id: g.id, name: g.name }))
        groupDropdownOpen.value = groupResults.value.length > 0
      } catch { /* silent */ }
    }, 250)
  }

  function addGroup(g: GroupItem): void {
    selectedGroups.value.push(g)
    form.group_ids.push(g.id)
    groupSearch.value = ''
    groupResults.value = []
    groupDropdownOpen.value = false
  }

  function removeGroup(id: string): void {
    selectedGroups.value = selectedGroups.value.filter(g => g.id !== id)
    form.group_ids = form.group_ids.filter(gid => gid !== id)
  }

  // ── Modal lifecycle ──────────────────────────────────────────────────

  function resetAll(): void {
    reset(emptyForm())
    selectedRoles.value  = []
    selectedGroups.value = []
    roleSearch.value     = ''
    groupSearch.value    = ''
    roleResults.value    = []
    groupResults.value   = []
    roleDropdownOpen.value  = false
    groupDropdownOpen.value = false
  }

  function openCreate(): void {
    editing.value = null
    schemaRef.value = userSchema(false)
    resetAll()
    showModal.value = true
  }

  async function openEdit(u: User): Promise<void> {
    editing.value = u
    schemaRef.value = userSchema(true)
    resetAll()
    showModal.value  = true
    modalLoading.value = true
    try {
      const fresh = await userRepository.find(u.id)
      editing.value = fresh
      reset({
        name: fresh.name, fio: fresh.fio ?? '', email: fresh.email,
        login: fresh.login ?? '', external_id: fresh.external_id ?? '',
        password: '', roles: fresh.roles.map(r => r.name),
        group_ids: fresh.groups.map(g => g.id),
      })
      selectedRoles.value  = fresh.roles.map(r => ({ id: r.id, name: r.name, title: r.title }))
      selectedGroups.value = fresh.groups.map(g => ({ id: g.id, name: g.name }))
    } catch (e: unknown) {
      formError.value = e instanceof Error ? e.message : 'Не удалось загрузить пользователя.'
    } finally {
      modalLoading.value = false
    }
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
          email: data.email,
          ...(data.fio          && { fio: data.fio }),
          ...(data.login        && { login: data.login }),
          ...(data.external_id  && { external_id: data.external_id }),
          ...(data.password     && { password: data.password }),
          roles:     data.roles,
          group_ids: data.group_ids,
        }
        if (editing.value) {
          await userRepository.update(editing.value.id, payload)
        } else {
          await userRepository.create(payload)
        }
      })
      close()
      onSaved()
      formToast.saved(isUpdate)
    } catch { /* отобразили ошибки */ }
  }

  // ── Delete confirm ───────────────────────────────────────────────────

  const confirmDelete = ref<User | null>(null)
  const deleting      = ref(false)
  const deleteError   = ref<string | null>(null)

  function openDeleteConfirm(u: User): void {
    confirmDelete.value = u
    deleteError.value   = null
  }

  function closeDeleteConfirm(): void {
    confirmDelete.value = null
    deleteError.value   = null
  }

  async function doDelete(): Promise<void> {
    if (!confirmDelete.value) return
    deleting.value = true
    deleteError.value = null
    try {
      await userRepository.remove(confirmDelete.value.id)
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

  return {
    showModal, editing, form, errors, formError, submitting, modalLoading,
    selectedRoles, roleSearch, roleDropdownOpen, roleResults,
    selectedGroups, groupSearch, groupDropdownOpen, groupResults,
    onRoleInput, addRole, removeRole,
    onGroupInput, addGroup, removeGroup,
    openCreate, openEdit, close, save,
    confirmDelete, deleting, deleteError,
    openDeleteConfirm, closeDeleteConfirm, doDelete,
  }
}
