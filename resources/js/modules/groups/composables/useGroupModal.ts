import {nextTick, ref, watch} from 'vue'
import {groupRepository} from '@/modules/groups/repositories/groupRepository'
import {toSlug} from '@/lib/slug'
import type {Group, GroupMember} from '@/modules/groups/types/group'
import {useFormToast} from '@/composables/useFormToast'
import {useZodForm} from '@/composables/useZodForm'
import {groupSchema} from '@/modules/groups/schemas/groupSchema'

export function useGroupModal(onSaved: () => void) {
    const showModal = ref(false)
    const editing = ref<Group | null>(null)

    const {formData: form, errors, formError, submitting, submit, reset} =
        useZodForm(groupSchema, {name: '', slug: '', description: '', is_active: true})

    watch(() => form.name, (name) => {
        if (!editing.value) form.slug = toSlug(name)
    })

    const formToast = useFormToast({
        created: 'Группа создана',
        updated: 'Группа обновлена',
        deleted: 'Группа удалена',
    })

    // ── Members ──────────────────────────────────────────────────────────

    const members = ref<GroupMember[]>([])
    const loadingMembers = ref(false)
    const memberSearch = ref('')
    const memberResults = ref<GroupMember[]>([])
    const memberSearchOpen = ref(false)
    let memberSearchTimer: ReturnType<typeof setTimeout> | null = null

    async function loadMembers(groupId: string): Promise<void> {
        loadingMembers.value = true
        try {
            members.value = await groupRepository.listMembers(groupId)
        } catch { /* silent */
        } finally {
            loadingMembers.value = false
        }
    }

    function onMemberSearchInput(): void {
        memberSearchOpen.value = false
        if (memberSearchTimer) clearTimeout(memberSearchTimer)
        if (!memberSearch.value.trim()) {
            memberResults.value = [];
            return
        }
        memberSearchTimer = setTimeout(async () => {
            try {
                const results = await groupRepository.searchUsers(memberSearch.value)
                memberResults.value = results.filter(u => !members.value.some(m => m.id === u.id))
                memberSearchOpen.value = memberResults.value.length > 0
            } catch { /* silent */
            }
        }, 250)
    }

    async function addMember(user: GroupMember): Promise<void> {
        if (!editing.value) return
        try {
            await groupRepository.addMember(editing.value.id, Number(user.id))
            members.value.push(user)
            editing.value.members_count++
            memberSearch.value = ''
            memberResults.value = []
            memberSearchOpen.value = false
        } catch (e: unknown) {
            formToast.error(e)
        }
    }

    async function removeMember(user: GroupMember): Promise<void> {
        if (!editing.value) return
        try {
            await groupRepository.removeMember(editing.value.id, user.id)
            members.value = members.value.filter(m => m.id !== user.id)
            editing.value.members_count = Math.max(0, editing.value.members_count - 1)
        } catch (e: unknown) {
            formToast.error(e)
        }
    }

    // ── Modal lifecycle ──────────────────────────────────────────────────

    function openCreate(): void {
        editing.value = null
        reset({name: '', slug: '', description: '', is_active: true})
        showModal.value = true
    }

    function openEdit(g: Group): void {
        editing.value = g
        reset({name: g.name, slug: g.slug, description: g.description ?? '', is_active: g.is_active})
        members.value = []
        memberSearch.value = ''
        memberResults.value = []
        memberSearchOpen.value = false
        showModal.value = true
        nextTick(() => loadMembers(g.id))
    }

    function close(): void {
        showModal.value = false
        editing.value = null
    }

    async function save(): Promise<void> {
        const isUpdate = editing.value !== null
        try {
            await submit(async (data) => {
                if (editing.value) {
                    await groupRepository.update(editing.value.id, {...data})
                } else {
                    await groupRepository.create({...data})
                }
            })
            close()
            onSaved()
            formToast.saved(isUpdate)
        } catch { /* errors уже в форме */
        }
    }

    async function toggleActive(g: Group): Promise<void> {
        try {
            await groupRepository.update(g.id, {
                name: g.name, slug: g.slug,
                description: g.description ?? '',
                ext_id: g.ext_id,
                is_active: !g.is_active,
            })
            g.is_active = !g.is_active
            formToast.saved(true)
        } catch (e: unknown) {
            formToast.error(e, 'Ошибка обновления.')
        }
    }

    // ── Delete confirm ───────────────────────────────────────────────────

    const confirmDelete = ref<Group | null>(null)
    const deleting = ref(false)
    const deleteError = ref<string | null>(null)

    function openDeleteConfirm(g: Group): void {
        confirmDelete.value = g
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
            await groupRepository.remove(confirmDelete.value.id)
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
        showModal, editing, form, errors, formError, submitting,
        members, loadingMembers, memberSearch, memberResults, memberSearchOpen,
        onMemberSearchInput, addMember, removeMember,
        openCreate, openEdit, close, save, toggleActive,
        confirmDelete, deleting, deleteError,
        openDeleteConfirm, closeDeleteConfirm, doDelete,
    }
}
