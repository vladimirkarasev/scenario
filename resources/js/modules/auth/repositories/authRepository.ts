import {getJson, sendJson} from '@/lib/http'
import type {AuthTokens, AuthUser} from '@/modules/auth/types/auth'

interface AuthUserResponse {
    data: AuthUser
}

export const authRepository = {
    async currentUser(): Promise<AuthUser> {
        const response = await getJson<AuthUserResponse>('/api/user', 'Не удалось загрузить пользователя.')
        return response.data
    },

    exchangeLaunchToken(token: string): Promise<AuthTokens> {
        return sendJson<AuthTokens>('/api/embed/auth/exchange', {
            method: 'POST',
            body: {_token: token},
            fallbackMessage: 'Не удалось выполнить вход.',
        })
    },
}
