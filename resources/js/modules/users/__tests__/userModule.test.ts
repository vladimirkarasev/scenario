import {beforeEach, describe, expect, it, vi} from 'vitest'
import {userSchema} from '@/modules/users/schemas/userSchema'
import {userRepository} from '@/modules/users/repositories/userRepository'
import {tokenRepository} from '@/modules/users/repositories/tokenRepository'
import {useUserTokens} from '@/modules/users/composables/useUserTokens'
import {useUserModal} from '@/modules/users/composables/useUserModal'
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
    login: 'ivan',
    external_id: null,
    created_at: null,
    roles: [],
    groups: [],
}

describe('users module', () => {
    beforeEach(() => vi.clearAllMocks())

    it('требует логин и email, пароль необязателен', () => {
        const value = {
            name: 'Иван',
            fio: '',
            email: 'ivan@example.test',
            login: 'ivan',
            external_id: '',
            password: '',
            roles: [],
            group_ids: [],
        }
        // Валидная форма без пароля проходит и при создании, и при редактировании.
        expect(userSchema(false).safeParse(value).success).toBe(true)
        expect(userSchema(true).safeParse(value).success).toBe(true)
        // Логин обязателен.
        expect(userSchema(false).safeParse({...value, login: ''}).success).toBe(false)
        // Email обязателен и должен быть валидным.
        expect(userSchema(false).safeParse({...value, email: 'invalid'}).success).toBe(false)
        // Короткий пароль отклоняется.
        expect(userSchema(false).safeParse({...value, password: 'short'}).success).toBe(false)
    })

    it('нормализует пользователя из JSON:API included', async () => {
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
                    roles: {data: [{type: 'roles', id: '1'}]},
                    groups: {data: [{type: 'groups', id: 'g1'}]},
                },
            },
            included: [
                {type: 'roles', id: '1', attributes: {name: 'admin', title: 'Администратор'}},
                {type: 'groups', id: 'g1', attributes: {name: 'Операторы', slug: 'operators'}},
            ],
        })

        await expect(userRepository.find('user-1')).resolves.toMatchObject({
            id: 'user-1',
            roles: [{id: '1', name: 'admin', title: 'Администратор'}],
            groups: [{id: 'g1', name: 'Операторы', slug: 'operators'}],
        })
    })

    it('сохраняет редактирование без обязательной смены пароля', async () => {
        vi.spyOn(userRepository, 'find').mockResolvedValue(user)
        vi.spyOn(userRepository, 'update').mockResolvedValue()
        const onSaved = vi.fn()
        const modal = useUserModal(onSaved)

        await modal.openEdit(user)
        modal.form.name = 'Иван Обновлённый'
        await modal.save()

        expect(userRepository.update).toHaveBeenCalledWith('user-1', expect.objectContaining({
            name: 'Иван Обновлённый',
        }))
        expect(onSaved).toHaveBeenCalledOnce()
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
