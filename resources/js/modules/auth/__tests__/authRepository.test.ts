import {beforeEach, describe, expect, it, vi} from 'vitest'
import {getJson, sendJson} from '@/lib/http'
import {authRepository} from '@/modules/auth/repositories/authRepository'

vi.mock('@/lib/http', () => ({
    getJson: vi.fn(),
    sendJson: vi.fn(),
}))

describe('authRepository', () => {
    beforeEach(() => vi.clearAllMocks())

    it('нормализует envelope текущего пользователя', async () => {
        const user = {id: 1, name: 'Admin', email: 'admin@example.test', project_id: null, permissions: []}
        vi.mocked(getJson).mockResolvedValue({data: user})

        await expect(authRepository.currentUser()).resolves.toEqual(user)
    })

    it('обменивает launch token через HTTP-адаптер', async () => {
        const tokens = {access_token: 'access', refresh_token: 'refresh'}
        vi.mocked(sendJson).mockResolvedValue(tokens)

        await expect(authRepository.exchangeLaunchToken('launch')).resolves.toEqual(tokens)
        expect(sendJson).toHaveBeenCalledWith('/api/embed/auth/exchange', {
            method: 'POST',
            body: {_token: 'launch'},
            fallbackMessage: 'Не удалось выполнить вход.',
        })
    })
})
