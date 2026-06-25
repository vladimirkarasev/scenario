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
        const base = {name: 'Операторы', description: '', is_active: true}
        expect(groupSchema.safeParse({...base, slug: 'call-center-2'}).success).toBe(true)
        expect(groupSchema.safeParse({...base, slug: 'call_center'}).success).toBe(false)
    })

    it('нормализует группы и участников', async () => {
        vi.mocked(getJson)
            .mockResolvedValueOnce({data: [rawGroup], meta: {total: 1}})
            .mockResolvedValueOnce({data: [{
                id: '10',
                attributes: {name: 'Иван', email: 'ivan@example.test', login: null},
            }]})

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
        await expect(groupRepository.listMembers('group-1')).resolves.toEqual([{
            id: '10',
            name: 'Иван',
            email: 'ivan@example.test',
            login: null,
        }])
    })

    it('кодирует поиск пользователей и формирует member endpoints', async () => {
        vi.mocked(getJson).mockResolvedValue({data: []})
        vi.mocked(sendJson).mockResolvedValue(undefined)
        vi.mocked(destroyJson).mockResolvedValue(null)

        await groupRepository.searchUsers('Иван + Пётр')
        await groupRepository.addMember('group-1', 10)
        await groupRepository.removeMember('group-1', '10')

        expect(getJson).toHaveBeenCalledWith(
            '/api/users?search=%D0%98%D0%B2%D0%B0%D0%BD%20%2B%20%D0%9F%D1%91%D1%82%D1%80&per_page=10',
            '',
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
