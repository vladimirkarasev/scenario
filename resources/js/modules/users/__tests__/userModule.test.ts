import {beforeEach, describe, expect, it, vi} from 'vitest'
import {userSchema} from '@/modules/users/schemas/userSchema'
import {userRepository} from '@/modules/users/repositories/userRepository'
import {tokenRepository} from '@/modules/users/repositories/tokenRepository'
import {useUserTokens} from '@/modules/users/composables/useUserTokens'
import {destroyJson, getJson, sendJson} from '@/lib/http'

vi.mock('@/lib/http', () => ({
    destroyJson: vi.fn(),
    getJson: vi.fn(),
    sendJson: vi.fn(),
}))

const user = {
    id: 'user-1',
    name: 'Иван',
    fio: null,
    email: 'ivan@example.test',
    login: null,
    external_id: null,
    created_at: null,
    roles: [],
    groups: [],
}

describe('users module', () => {
    beforeEach(() => vi.clearAllMocks())

    it('требует пароль только при создании', () => {
        const value = {
            name: 'Иван',
            fio: '',
            email: 'ivan@example.test',
            login: '',
            external_id: '',
            password: '',
            roles: [],
            group_ids: [],
        }
        expect(userSchema(false).safeParse(value).success).toBe(false)
        expect(userSchema(true).safeParse(value).success).toBe(true)
        expect(userSchema(true).safeParse({...value, email: 'invalid'}).success).toBe(false)
    })

    it('нормализует relationships пользователя с defaults', async () => {
        vi.mocked(getJson).mockResolvedValue({
            data: {
                id: 'user-1',
                attributes: {
                    name: 'Иван',
                    fio: null,
                    email: 'ivan@example.test',
                    login: null,
                    external_id: null,
                    created_at: null,
                },
                relationships: {
                    roles: {data: [{id: '1', meta: {name: 'admin'}}]},
                    groups: {data: [{id: 'g1', meta: {name: 'Операторы'}}]},
                },
            },
        })

        await expect(userRepository.find('user-1')).resolves.toMatchObject({
            id: 'user-1',
            roles: [{id: '1', name: 'admin', title: null}],
            groups: [{id: 'g1', name: 'Операторы', slug: ''}],
        })
    })

    it('формирует token endpoints и nullable expires_at', async () => {
        vi.mocked(sendJson).mockResolvedValue({data: {id: 1, name: 'CLI', token: 'secret'}})
        vi.mocked(destroyJson).mockResolvedValue(null)

        await tokenRepository.create('user-1', 'CLI', null)
        await tokenRepository.revoke('user-1', 1)

        expect(sendJson).toHaveBeenCalledWith('/api/users/user-1/tokens', {
            method: 'POST',
            body: {name: 'CLI', expires_at: null},
            fallbackMessage: 'Не удалось создать токен.',
        })
        expect(destroyJson).toHaveBeenCalledWith(
            '/api/users/user-1/tokens/1',
            'Не удалось отозвать токен.',
        )
    })

    it('управляет жизненным циклом токенов в composable', async () => {
        vi.spyOn(tokenRepository, 'list').mockResolvedValue([])
        vi.spyOn(tokenRepository, 'create').mockResolvedValue({
            id: 2,
            name: 'CLI',
            plain_text_token: 'plain-token',
            abilities: ['*'],
            expires_at: null,
            last_used_at: null,
            created_at: null,
        })
        vi.spyOn(tokenRepository, 'revoke').mockResolvedValue()
        const tokens = useUserTokens()

        await tokens.open(user)
        tokens.newTokenName.value = '  CLI  '
        tokens.newTokenExpiresAt.value = 'never'
        await tokens.create()

        expect(tokenRepository.create).toHaveBeenCalledWith('user-1', 'CLI', null)
        expect(tokens.tokens.value).toHaveLength(1)
        expect(tokens.createdToken.value?.plain_text_token).toBe('plain-token')

        await tokens.revoke(2)
        expect(tokens.tokens.value).toEqual([])
        expect(tokens.createdToken.value).toBeNull()
    })
})
