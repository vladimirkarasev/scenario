import {getCurrentScope, onScopeDispose, ref} from 'vue'
import type {Ref} from 'vue'

export interface RequestConcurrencyPolicy {
    begin(): number
    isCurrent(requestId: number): boolean
    invalidate(): void
}

export class LastStartedWinsPolicy implements RequestConcurrencyPolicy {
    private sequence = 0

    begin(): number {
        return ++this.sequence
    }

    isCurrent(requestId: number): boolean {
        return requestId === this.sequence
    }

    invalidate(): void {
        this.sequence++
    }
}

export interface UseLatestRequestReturn {
    loading: Ref<boolean>
    error: Ref<string | null>
    execute: <T>(task: () => Promise<T>) => Promise<T | undefined>
    cancel: () => void
}

export function useLatestRequest(
    fallbackMessage: string,
    policy: RequestConcurrencyPolicy = new LastStartedWinsPolicy(),
): UseLatestRequestReturn {
    const loading = ref(false)
    const error = ref<string | null>(null)

    async function execute<T>(task: () => Promise<T>): Promise<T | undefined> {
        const requestId = policy.begin()
        loading.value = true
        error.value = null

        try {
            const result = await task()
            return policy.isCurrent(requestId) ? result : undefined
        } catch (caught: unknown) {
            if (policy.isCurrent(requestId)) {
                error.value = caught instanceof Error ? caught.message : fallbackMessage
            }
            return undefined
        } finally {
            if (policy.isCurrent(requestId)) {
                loading.value = false
            }
        }
    }

    function cancel(): void {
        policy.invalidate()
        loading.value = false
    }

    if (getCurrentScope()) {
        onScopeDispose(cancel)
    }

    return {loading, error, execute, cancel}
}
