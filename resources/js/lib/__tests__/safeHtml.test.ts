import {describe, expect, it} from 'vitest'
import {isSafeHtmlUrl, sanitizeHtml} from '@/lib/safe-html'

describe('isSafeHtmlUrl', () => {
    it.each(['https://example.test', 'http://example.test', 'mailto:user@example.test', 'tel:+79990000000', '/relative', '#anchor'])(
        'разрешает безопасный URL %s',
        (url) => expect(isSafeHtmlUrl(url)).toBe(true),
    )

    it.each(['javascript:alert(1)', 'data:text/html,<script>alert(1)</script>', 'vbscript:msgbox(1)', 'java\nscript:alert(1)'])(
        'блокирует опасный URL %s',
        (url) => expect(isSafeHtmlUrl(url)).toBe(false),
    )
})

describe('sanitizeHtml', () => {
    it('в SSR-окружении закрывается безопасно', () => {
        expect(sanitizeHtml('<img src=x onerror=alert(1)>')).toBe('')
    })
})
