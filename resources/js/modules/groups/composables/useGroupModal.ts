import {nextTick, ref} from 'vue'
import {groupRepository} from '@/modules/groups/repositories/groupRepository'
import type {Group, GroupMember} from '@/modules/groups/types/group'
import type {PaginationMeta} from '@/types/pagination'
import {useFormToast} from '@/composables/useFormToast'
import {useZodForm} from '@/composables/useZodForm'
import {groupSchema} from '@/modules/groups/schemas/groupSchema'

export function useGroupModal(onSaved: () => void) {
    const showModal = ref(false)
    const editing = ref<Group | null>(null)

    const {formData: form, errors, formError, submitting, submit, reset} =
        useZodForm(groupSchema, {name: '', slug: '', ext_id: '', description: '', is_active: true})

    const formToast = useFormToast({
        created: 'Группа создана',
        updated: 'Группа обновлена',
        deleted: 'Группа удалена',
    })

    // ── Members ──────────────────────────────────────────────────────────

    const members = ref<GroupMember[]>([])
    const membersMeta = ref<PaginationMeta>({current_page: 1, last_page: 1, per_page: 20, total: 0})
    const loadingMembers = ref(false)
    const memberLoadError = ref<string | null>(null)
    const memberSearch = ref('')
    const memberResults = ref<GroupMember[]>([])
    const memberSearchOpen = ref(false)
    const memberSearchError = ref<string | null>(null)
    let memberSearchTimer: ReturnType<typeof setTimeout> | null = null
    let memberSearchRequest = 0
    let membersRequest = 0

    async function loadMembers(groupId: string, page = 1, append = false): Promise<void> {
        const request = ++membersRequest
        loadingMembers.value = true
        memberLoadError.value = null
        try {
            const result = await groupRepository.listMembers(groupId, page)
            if (request === membersRequest && editing.value?.id === groupId) {
                members.value = append ? [...members.value, ...result.data] : result.data
                membersMeta.value = result.meta
            }
        } catch (e: unknown) {
            if (request === membersRequest) {
                memberLoadError.value = e instanceof Error ? e.message : 'Не удалось загрузить участников.'
            }
        } finally {
            if (request === membersRequest) loadingMembers.value = false
        }
    }

    function loadMoreMembers(): void {
        if (!editing.value || loadingMembers.value) return
        if (membersMeta.value.current_page >= membersMeta.value.last_page) return
        loadMembers(editing.value.id, membersMeta.value.current_page + 1, true)
    }

    function onMemberSearchInput(): void {
        const request = ++memberSearchRequest
        memberSearchOpen.value = false
        memberSearchError.value = null
        if (memberSearchTimer) clearTimeout(memberSearchTimer)
        const search = memberSearch.value.trim()
        const groupId = editing.value?.id
        if (!search || !groupId) {
            memberResults.value = [];
            return
        }
        memberSearchTimer = setTimeout(async () => {
            try {
                const results = await groupRepository.searchUsers(groupId, search)
                if (request !== memberSearchRequest || editing.value?.id !== groupId) return
                memberResults.value = results
                memberSearchOpen.value = memberResults.value.length > 0
            } catch (e: unknown) {
                if (request === memberSearchRequest) {
                    memberSearchError.value = e instanceof Error ? e.message : 'Не удалось найти пользователей.'
                }
            }
        }, 250)
    }

    async function addMember(user: GroupMember): Promise<void> {
        if (!editing.value) return
        try {
            await groupRepository.addMember(editing.value.id, Number(user.id))
            members.value.push(user)
            editing.value.members_count++
            membersMeta.value.total++
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
            membersMeta.value.total = Math.max(0, membersMeta.value.total - 1)
        } catch (e: unknown) {
            formToast.error(e)
        }
    }

    // ── Modal lifecycle ──────────────────────────────────────────────────

    function openCreate(): void {
        editing.value = null
        reset({name: '', slug: '', ext_id: '', description: '', is_active: true})
        showModal.value = true
    }

    function openEdit(g: Group): void {
        editing.value = g
        reset({
            name: g.name,
            slug: g.slug,
            ext_id: g.ext_id ?? '',
            description: g.description ?? '',
            is_active: g.is_active,
        })
        members.value = []
        membersMeta.value = {current_page: 1, last_page: 1, per_page: 20, total: 0}
        memberLoadError.value = null
        memberSearch.value = ''
        memberResults.value = []
        memberSearchOpen.value = false
        memberSearchError.value = null
        showModal.value = true
        nextTick(() => loadMembers(g.id))
    }

    function close(): void {
        memberSearchRequest++
        membersRequest++
        if (memberSearchTimer) clearTimeout(memberSearchTimer)
        showModal.value = false
        editing.value = null
        loadingMembers.value = false
        memberLoadError.value = null
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
        members, membersMeta, loadingMembers, memberLoadError,
        memberSearch, memberResults, memberSearchOpen, memberSearchError,
        onMemberSearchInput, loadMoreMembers, addMember, removeMember,
        openCreate, openEdit, close, save, toggleActive,
        confirmDelete, deleting, deleteError,
        openDeleteConfirm, closeDeleteConfirm, doDelete,
    }
}
