const TEMPLATE_RE = /\{\{\s*([\w.-]+)\s*\}\}/g

function resolvePath(source: unknown, path: string): unknown {
    const parts = path.split('.')
    let current: unknown = source
    for (const part of parts) {
        if (current && typeof current === 'object' && part in (current as Record<string, unknown>)) {
            current = (current as Record<string, unknown>)[part]
        } else {
            return undefined
        }
    }
    return current
}

export function renderLabelTemplate(
    template: string,
    data: Record<string, unknown>,
    context: Record<string, unknown> = {},
): string {
    if (!template) return ''
    const result = template.replace(TEMPLATE_RE, (_, key: string) => {
        if (key in data) return String(data[key] ?? '')
        const fromContext = resolvePath(context, key)
        if (fromContext === undefined || fromContext === null) return ''
        return typeof fromContext === 'object' ? JSON.stringify(fromContext) : String(fromContext)
    })
    return result.trim() ? result : ''
}

export function extractTemplateKeys(template: string): string[] {
    return [...template.matchAll(TEMPLATE_RE)].map((m) => m[1])
}
