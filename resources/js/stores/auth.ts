import {getJson} from '@/lib/http'
import {redirectToPartner} from '@/lib/auth-redirect'
import {defineStore} from 'pinia'
import {ref} from 'vue'

interface AuthUser {
    id: number
    name: string
    email: string
    project_id: string | null
    permissions: string[]
}

export const useAuthStore = defineStore('auth', () => {
    const user = ref<AuthUser | null>(null)
    const ready = ref(false)

    // Дедуп: параллельные вызовы (глобальный bootstrap в app.ts + страница) делят
    // один запрос /api/user. После завершения сбрасываем — повторная инициализация
    // (напр. после логина) снова сходит на сервер.
    let inFlight: Promise<void> | null = null

    function initialize(): Promise<void> {
        if (inFlight) return inFlight

        inFlight = loadUser().finally(() => {
            inFlight = null
        })

        return inFlight
    }

    async function loadUser(): Promise<void> {
        const token = sessionStorage.getItem('access_token')
        const refreshToken = sessionStorage.getItem('refresh_token')
        if (!token && !refreshToken) {
            redirectToPartner()
            ready.value = true
            return
        }
        try {
            user.value = await getJson('/api/user', 'Failed to load user') as AuthUser
        } catch {
            sessionStorage.removeItem('access_token')
        } finally {
            ready.value = true
        }
    }

    function hasPermission(permission: string): boolean {
        return user.value?.permissions.includes(permission) ?? false
    }

    return {user, ready, initialize, hasPermission}
})
