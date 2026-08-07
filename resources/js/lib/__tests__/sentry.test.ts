import {describe, expect, it} from 'vitest'
import {shouldInitializeSentry} from '@/lib/sentry'

describe('shouldInitializeSentry', () => {
    it('enables Sentry for a production build with a DSN', () => {
        expect(shouldInitializeSentry('https://public@example.test/1', true)).toBe(true)
    })

    it('does not initialize Sentry in development', () => {
        expect(shouldInitializeSentry('https://public@example.test/1', false)).toBe(false)
    })

    it.each([undefined, '', '   '])('does not initialize Sentry without a DSN', (dsn) => {
        expect(shouldInitializeSentry(dsn, true)).toBe(false)
    })
})
