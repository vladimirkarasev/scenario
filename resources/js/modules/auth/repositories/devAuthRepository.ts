import {sendJson} from '@/lib/http'
import type {DevAuthFormValues} from '@/modules/auth/schemas/devAuthSchema'
import type {DevAuthLaunchToken} from '@/modules/auth/types/devAuth'

interface DevAuthResponse {
    data: {
        _token: string
        expires_in: number
    }
}

export const devAuthRepository = {
    async impersonate(values: DevAuthFormValues): Promise<DevAuthLaunchToken> {
        const response = await sendJson<DevAuthResponse>('/api/dev/auth/impersonate', {
            method: 'POST',
            body: {
                project_id: values.projectId,
                user_id: Number(values.userId),
            },
            fallbackMessage: 'Не удалось выполнить вход.',
        })

        return {
            token: response.data._token,
            expiresIn: response.data.expires_in,
        }
    },
}
