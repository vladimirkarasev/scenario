import {getJson} from '@/lib/http'
import {redirectToPartner} from '@/lib/auth-redirect'
import {defineStore} from 'pinia'
import {ref} from 'vue'

interface AuthUser {
    id: number
    name: string
    email: string
    permissions: string[]
}

export const useAuthStore = defineStore('auth', () => {
    const user = ref<AuthUser | null>(null)
    const ready = ref(false)

    async function initialize(): Promise<void> {
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
