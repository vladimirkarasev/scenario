import {beforeEach, describe, expect, it, vi} from 'vitest'
import {roleSchema} from '@/modules/roles/schemas/roleSchema'
import {roleRepository} from '@/modules/roles/repositories/roleRepository'
import {destroyJson, getJson, sendJson} from '@/lib/http'

vi.mock('@/lib/http', () => ({
    destroyJson: vi.fn(),
    getJson: vi.fn(),
    sendJson: vi.fn(),
}))

const rawRole = {
    id: '12',
    attributes: {
        name: 'content_manager',
        title: null,
        description: null,
        is_system: true,
        guard_name: 'web',
        permissions: ['scenario.edit'],
        created_at: null,
    },
    relationships: {users: {meta: {count: 5}}},
}

describe('roles module', () => {
    beforeEach(() => vi.clearAllMocks())

    it.each(['manager', 'content_manager2'])('принимает системное имя %s', (name) => {
        expect(roleSchema.safeParse({name, title: '', description: '', permissions: []}).success).toBe(true)
    })

    it.each(['', '2manager', 'ContentManager', 'content-manager'])('отклоняет системное имя %s', (name) => {
        expect(roleSchema.safeParse({name, title: '', description: '', permissions: []}).success).toBe(false)
    })

    it('нормализует числовой id, defaults и users_count', async () => {
        vi.mocked(getJson).mockResolvedValue({data: [rawRole]})

        await expect(roleRepository.list(new URLSearchParams())).resolves.toEqual([{
            id: 12,
            name: 'content_manager',
            title: null,
            description: null,
            is_system: true,
            users_count: 5,
            permissions: ['scenario.edit'],
        }])
    })

    it('создаёт и удаляет роль через правильные endpoints', async () => {
        const payload = {name: 'manager', title: '', description: '', permissions: []}
        vi.mocked(sendJson).mockResolvedValue({data: rawRole})
        vi.mocked(destroyJson).mockResolvedValue(null)

        await roleRepository.create(payload)
        await roleRepository.remove(12)

        expect(sendJson).toHaveBeenCalledWith('/api/roles', {
            method: 'POST',
            body: payload,
            fallbackMessage: 'Не удалось создать роль.',
        })
        expect(destroyJson).toHaveBeenCalledWith('/api/roles/12', 'Не удалось удалить роль.')
    })
})
