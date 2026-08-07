const ALLOWED_ELEMENTS = new Set([
    'a', 'blockquote', 'br', 'code', 'details', 'div', 'em', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
    'hr', 'li', 'mark', 'ol', 'p', 'pre', 's', 'span', 'strong', 'summary', 'table', 'tbody', 'td',
    'th', 'thead', 'tr', 'u', 'ul',
])

const REMOVED_WITH_CONTENT = new Set(['iframe', 'math', 'object', 'script', 'style', 'svg', 'template'])
const ALLOWED_ATTRIBUTES: Record<string, Set<string>> = {
    a: new Set(['href', 'rel', 'target']),
    td: new Set(['colspan', 'rowspan']),
    th: new Set(['colspan', 'rowspan']),
}

export function isSafeHtmlUrl(value: string): boolean {
    const normalized = [...value.trim()]
        .filter(character => {
            const code = character.charCodeAt(0)
            return code > 31 && code !== 127 && !/\s/.test(character)
        })
        .join('')
    if (normalized === '') return false
    if (normalized.startsWith('#') || normalized.startsWith('/') || normalized.startsWith('./') || normalized.startsWith('../')) {
        return true
    }
    try {
        const parsed = new URL(normalized)
        return ['http:', 'https:', 'mailto:', 'tel:'].includes(parsed.protocol)
    } catch {
        return false
    }
}

function sanitizeElement(element: Element): void {
    const tag = element.tagName.toLowerCase()
    if (!ALLOWED_ELEMENTS.has(tag)) {
        if (REMOVED_WITH_CONTENT.has(tag)) {
            element.remove()
        } else {
            element.replaceWith(...element.childNodes)
        }
        return
    }

    const allowed = ALLOWED_ATTRIBUTES[tag] ?? new Set<string>()
    for (const attribute of [...element.attributes]) {
        if (!allowed.has(attribute.name.toLowerCase())) {
            element.removeAttribute(attribute.name)
        }
    }

    if (tag === 'a') {
        const href = element.getAttribute('href')
        if (href !== null && !isSafeHtmlUrl(href)) {
            element.removeAttribute('href')
        }
        if (element.getAttribute('target') === '_blank') {
            element.setAttribute('rel', 'noopener noreferrer')
        } else {
            element.removeAttribute('target')
            element.removeAttribute('rel')
        }
    }
}

export function sanitizeHtml(value: string): string {
    if (value === '' || typeof DOMParser === 'undefined') return ''
    const document = new DOMParser().parseFromString(value, 'text/html')
    for (const element of [...document.body.querySelectorAll('*')]) {
        sanitizeElement(element)
    }
    return document.body.innerHTML
}
