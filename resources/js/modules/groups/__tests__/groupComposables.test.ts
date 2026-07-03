import {nextTick} from 'vue'
import {beforeEach, describe, expect, it, vi} from 'vitest'
import {useGroupList} from '@/modules/groups/composables/useGroupList'
import {useGroupModal} from '@/modules/groups/composables/useGroupModal'
import {groupRepository} from '@/modules/groups/repositories/groupRepository'
import type {Group, GroupsPage} from '@/modules/groups/types/group'

vi.mock('vue', async (importOriginal) => {
    const actual = await importOriginal<typeof import('vue')>()

    return {
        ...actual,
        onMounted: (callback: () => void) => callback(),
        onBeforeUnmount: vi.fn(),
    }
})

vi.mock('@/modules/groups/repositories/groupRepository', () => ({
    groupRepository: {
        list: vi.fn(),
        listMembers: vi.fn(),
        searchUsers: vi.fn(),
        create: vi.fn(),
        update: vi.fn(),
        remove: vi.fn(),
        addMember: vi.fn(),
        removeMember: vi.fn(),
    },
}))

vi.mock('@/composables/useFormToast', () => ({
    useFormToast: () => ({
        saved: vi.fn(),
        deleted: vi.fn(),
        error: vi.fn(),
    }),
}))

const group = (overrides: Partial<Group> = {}): Group => ({
    id: 'group-1',
    name: 'Операторы',
    slug: 'operators',
    ext_id: null,
    description: null,
    is_active: true,
    members_count: 2,
    created_at: null,
    ...overrides,
})

const page = (items: Group[]): GroupsPage => ({
    data: items,
    meta: {current_page: 1, last_page: 1, per_page: 20, total: items.length},
})

async function flushPromises(): Promise<void> {
    await Promise.resolve()
    await nextTick()
    await Promise.resolve()
}

function deferred<T>(): {
    promise: Promise<T>
    resolve: (value: T) => void
} {
    let resolve!: (value: T) => void
    const promise = new Promise<T>((done) => {
        resolve = done
    })

    return {promise, resolve}
}

describe('group composables', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('показывает ошибку загрузки списка групп', async () => {
        vi.mocked(groupRepository.list).mockRejectedValue(new Error('Сервис недоступен'))

        const state = useGroupList()
        await flushPromises()

        expect(state.loading.value).toBe(false)
        expect(state.error.value).toBe('Сервис недоступен')
    })

    it('не применяет устаревший ответ списка групп', async () => {
        const first = deferred<GroupsPage>()
        const second = deferred<GroupsPage>()
        vi.mocked(groupRepository.list)
            .mockReturnValueOnce(first.promise)
            .mockReturnValueOnce(second.promise)

        const state = useGroupList()
        const reload = state.load()
        second.resolve(page([group({id: 'new'})]))
        await reload
        first.resolve(page([group({id: 'old'})]))
        await flushPromises()

        expect(state.groups.value.map(item => item.id)).toEqual(['new'])
    })

    it('добавляет следующую страницу участников', async () => {
        vi.mocked(groupRepository.listMembers)
            .mockResolvedValueOnce({
                data: [{id: '1', name: 'Первый', email: 'one@example.test', login: null}],
                meta: {current_page: 1, last_page: 2, per_page: 20, total: 2},
            })
            .mockResolvedValueOnce({
                data: [{id: '2', name: 'Второй', email: 'two@example.test', login: null}],
                meta: {current_page: 2, last_page: 2, per_page: 20, total: 2},
            })

        const modal = useGroupModal(vi.fn())
        modal.openEdit(group())
        await flushPromises()
        modal.loadMoreMembers()
        await flushPromises()

        expect(modal.members.value.map(member => member.id)).toEqual(['1', '2'])
        expect(groupRepository.listMembers).toHaveBeenLastCalledWith('group-1', 2)
    })

    it('не применяет ответ участников после закрытия modal', async () => {
        const request = deferred<Awaited<ReturnType<typeof groupRepository.listMembers>>>()
        vi.mocked(groupRepository.listMembers).mockReturnValue(request.promise)

        const modal = useGroupModal(vi.fn())
        modal.openEdit(group())
        await nextTick()
        modal.close()
        request.resolve({
            data: [{id: '1', name: 'Первый', email: 'one@example.test', login: null}],
            meta: {current_page: 1, last_page: 1, per_page: 20, total: 1},
        })
        await flushPromises()

        expect(modal.members.value).toEqual([])
        expect(modal.loadingMembers.value).toBe(false)
    })

    it('показывает ошибку загрузки участников', async () => {
        vi.mocked(groupRepository.listMembers).mockRejectedValue(new Error('Участники недоступны'))

        const modal = useGroupModal(vi.fn())
        modal.openEdit(group())
        await flushPromises()

        expect(modal.memberLoadError.value).toBe('Участники недоступны')
    })
})
