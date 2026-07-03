import {beforeEach, describe, expect, it, vi} from 'vitest'
import {groupSchema} from '@/modules/groups/schemas/groupSchema'
import {groupRepository} from '@/modules/groups/repositories/groupRepository'
import {destroyJson, getJson, sendJson} from '@/lib/http'

vi.mock('@/lib/http', () => ({
    destroyJson: vi.fn(),
    getJson: vi.fn(),
    sendJson: vi.fn(),
}))

const rawGroup = {
    id: 'group-1',
    attributes: {
        name: 'Операторы',
        slug: 'operators',
        ext_id: null,
        description: null,
        is_active: true,
        members_count: 2,
        created_at: null,
    },
}

describe('groups module', () => {
    beforeEach(() => vi.clearAllMocks())

    it('валидирует slug группы', () => {
        const base = {name: 'Операторы', ext_id: '', description: '', is_active: true}
        expect(groupSchema.safeParse({...base, slug: 'call-center-2'}).success).toBe(true)
        expect(groupSchema.safeParse({...base, slug: 'call_center'}).success).toBe(false)
        expect(groupSchema.safeParse({...base, slug: 'call center'}).success).toBe(false)
    })

    it('нормализует группы и участников', async () => {
        vi.mocked(getJson)
            .mockResolvedValueOnce({data: [rawGroup], meta: {total: 1}})
            .mockResolvedValueOnce({data: [{
                id: '10',
                attributes: {name: 'Иван', email: 'ivan@example.test', login: null},
            }], meta: {last_page: 1}})

        await expect(groupRepository.list(new URLSearchParams({page: '1'}))).resolves.toEqual({
            data: [{
                id: 'group-1',
                name: 'Операторы',
                slug: 'operators',
                ext_id: null,
                description: null,
                is_active: true,
                members_count: 2,
                created_at: null,
            }],
            meta: {total: 1},
        })
        await expect(groupRepository.listMembers('group-1')).resolves.toEqual({
            data: [{
                id: '10',
                name: 'Иван',
                email: 'ivan@example.test',
                login: null,
            }],
            meta: {last_page: 1},
        })
    })

    it('загружает выбранную страницу участников', async () => {
        const member = (id: string) => ({
            id,
            attributes: {name: `Участник ${id}`, email: `${id}@example.test`, login: null},
        })
        vi.mocked(getJson).mockResolvedValue({
            data: [member('2')],
            meta: {current_page: 2, last_page: 3, per_page: 20, total: 42},
        })

        await expect(groupRepository.listMembers('group-1', 2)).resolves.toMatchObject({
            data: [{id: '2'}],
            meta: {current_page: 2, last_page: 3},
        })
        expect(getJson).toHaveBeenCalledWith(
            '/api/groups/group-1/members?page[size]=20&page[number]=2',
            'Не удалось загрузить участников.',
        )
    })

    it('кодирует поиск пользователей и формирует member endpoints', async () => {
        vi.mocked(getJson).mockResolvedValue({data: []})
        vi.mocked(sendJson).mockResolvedValue(undefined)
        vi.mocked(destroyJson).mockResolvedValue(null)

        await groupRepository.searchUsers('group-1', 'Иван + Пётр')
        await groupRepository.addMember('group-1', 10)
        await groupRepository.removeMember('group-1', '10')

        expect(getJson).toHaveBeenCalledWith(
            '/api/groups/group-1/member-candidates?filter[search]=%D0%98%D0%B2%D0%B0%D0%BD%20%2B%20%D0%9F%D1%91%D1%82%D1%80&page[size]=10',
            'Не удалось найти пользователей.',
        )
        expect(sendJson).toHaveBeenCalledWith('/api/groups/group-1/members', {
            method: 'POST',
            body: {user_id: 10},
            fallbackMessage: 'Не удалось добавить участника.',
        })
        expect(destroyJson).toHaveBeenCalledWith(
            '/api/groups/group-1/members/10',
            'Не удалось удалить участника.',
        )
    })
})
