import {markForbidden} from '@/lib/auth-state'
import {defineStore} from 'pinia'
import {ref} from 'vue'
import {authRepository} from '@/modules/auth/repositories/authRepository'
import type {AuthUser} from '@/modules/auth/types/auth'

export const useAuthStore = defineStore('auth', () => {
    const user = ref<AuthUser | null>(null)
    const ready = ref(false)

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
            markForbidden()
            ready.value = true
            return
        }
        try {
            user.value = await authRepository.currentUser()
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
