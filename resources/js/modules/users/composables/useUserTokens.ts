import {ref} from 'vue'
import {tokenRepository} from '@/modules/users/repositories/tokenRepository'
import type {ApiToken, ApiTokenCreated} from '@/modules/users/types/user'
import type {User} from '@/modules/users/types/user'

export function useUserTokens() {
    const user = ref<User | null>(null)
    const showModal = ref(false)
    const tokens = ref<ApiToken[]>([])
    const loading = ref(false)
    const error = ref<string | null>(null)

    const newTokenName = ref('')
    const newTokenExpiresAt = ref<string>('')
    const creating = ref(false)
    const createError = ref<string | null>(null)
    const createdToken = ref<ApiTokenCreated | null>(null)

    const revokingId = ref<number | null>(null)

    async function open(u: User): Promise<void> {
        user.value = u
        showModal.value = true
        createdToken.value = null
        newTokenName.value = ''
        newTokenExpiresAt.value = ''
        createError.value = null
        await load()
    }

    function close(): void {
        showModal.value = false
        user.value = null
        tokens.value = []
        createdToken.value = null
        newTokenName.value = ''
        newTokenExpiresAt.value = ''
        error.value = null
        createError.value = null
    }

    async function load(): Promise<void> {
        if (!user.value) return
        loading.value = true
        error.value = null
        try {
            tokens.value = await tokenRepository.list(user.value.id)
        } catch (e: unknown) {
            error.value = e instanceof Error ? e.message : 'Ошибка загрузки.'
        } finally {
            loading.value = false
        }
    }

    async function create(): Promise<void> {
        if (!user.value || !newTokenName.value.trim()) return
        creating.value = true
        createError.value = null
        createdToken.value = null
        try {
            const raw = newTokenExpiresAt.value.trim()
            const expiresAt = (!raw || raw === 'never') ? null : raw
            const token = await tokenRepository.create(user.value.id, newTokenName.value.trim(), expiresAt)
            createdToken.value = token
            newTokenName.value = ''
            newTokenExpiresAt.value = ''
            tokens.value.unshift(token)
        } catch (e: unknown) {
            createError.value = e instanceof Error ? e.message : 'Не удалось создать токен.'
        } finally {
            creating.value = false
        }
    }

    async function revoke(tokenId: number): Promise<void> {
        if (!user.value) return
        revokingId.value = tokenId
        try {
            await tokenRepository.revoke(user.value.id, tokenId)
            tokens.value = tokens.value.filter(t => t.id !== tokenId)
            if (createdToken.value?.id === tokenId) createdToken.value = null
        } catch (e: unknown) {
            error.value = e instanceof Error ? e.message : 'Не удалось отозвать токен.'
        } finally {
            revokingId.value = null
        }
    }

    function dismissCreatedToken(): void {
        createdToken.value = null
    }

    return {
        user, showModal, tokens, loading, error,
        newTokenName, newTokenExpiresAt, creating, createError, createdToken,
        revokingId,
        open, close, create, revoke, dismissCreatedToken,
    }
}
