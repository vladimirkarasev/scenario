import {effectScope} from 'vue'
import {describe, expect, it} from 'vitest'
import {LastStartedWinsPolicy, useLatestRequest} from '@/composables/useLatestRequest'

interface Deferred<T> {
    promise: Promise<T>
    resolve: (value: T) => void
    reject: (reason?: unknown) => void
}

function deferred<T>(): Deferred<T> {
    let resolve!: (value: T) => void
    let reject!: (reason?: unknown) => void
    const promise = new Promise<T>((resolvePromise, rejectPromise) => {
        resolve = resolvePromise
        reject = rejectPromise
    })
    return {promise, resolve, reject}
}

describe('LastStartedWinsPolicy', () => {
    it('делает актуальным только последний запрос', () => {
        const policy = new LastStartedWinsPolicy()
        const first = policy.begin()
        const second = policy.begin()

        expect(policy.isCurrent(first)).toBe(false)
        expect(policy.isCurrent(second)).toBe(true)
    })
})

describe('useLatestRequest', () => {
    it('не возвращает результат устаревшего запроса', async () => {
        const first = deferred<string>()
        const second = deferred<string>()
        const request = useLatestRequest('Ошибка')

        const firstResult = request.execute(() => first.promise)
        const secondResult = request.execute(() => second.promise)
        second.resolve('fresh')
        first.resolve('stale')

        await expect(secondResult).resolves.toBe('fresh')
        await expect(firstResult).resolves.toBeUndefined()
        expect(request.loading.value).toBe(false)
    })

    it('игнорирует ошибку устаревшего запроса', async () => {
        const first = deferred<string>()
        const second = deferred<string>()
        const request = useLatestRequest('Ошибка')

        const firstResult = request.execute(() => first.promise)
        const secondResult = request.execute(() => second.promise)
        first.reject(new Error('Старая ошибка'))
        second.resolve('fresh')

        await firstResult
        await secondResult
        expect(request.error.value).toBeNull()
    })

    it('инвалидирует запрос при уничтожении scope', async () => {
        const pending = deferred<string>()
        const scope = effectScope()
        const request = scope.run(() => useLatestRequest('Ошибка'))!
        const result = request.execute(() => pending.promise)

        scope.stop()
        pending.resolve('late')

        await expect(result).resolves.toBeUndefined()
        expect(request.loading.value).toBe(false)
    })
})
