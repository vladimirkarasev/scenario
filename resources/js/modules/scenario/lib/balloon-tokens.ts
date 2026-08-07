interface TiptapNode {
    type?: string
    text?: string
    content?: TiptapNode[]
    [key: string]: unknown
}

const TOKEN_PATTERN = /\{\{\s*(\w+)\s*\}\}/g

function resolveText(text: string, data: Record<string, string | null>): string {
    return text.replace(TOKEN_PATTERN, (_match, key: string) => data[key] ?? '')
}

function resolveNode(node: TiptapNode, data: Record<string, string | null>): TiptapNode {
    const next: TiptapNode = {...node}

    if (typeof next.text === 'string') {
        next.text = resolveText(next.text, data)
    }

    if (Array.isArray(next.content)) {
        next.content = next.content.map((child) => resolveNode(child, data))
    }

    return next
}

/**
 * Прямая подстановка {{ key }} → data[key] в TipTap-документе, без вычисления
 * выражений/фильтров — только точный lookup по ключу колонки справочника.
 */
export function resolveBalloonTokens(document: unknown, data: Record<string, string | null>): unknown {
    if (!document || typeof document !== 'object') return document

    return resolveNode(document as TiptapNode, data)
}
