export interface TextLayoutSegment {
    type: 'text'
    key: string
    document: { type: 'doc'; content: Record<string, unknown>[] }
}

export interface FieldLayoutSegment {
    type: 'field'
    key: string
    blockId: string
}

export type LayoutSegment = TextLayoutSegment | FieldLayoutSegment

function isEmptyParagraph(node: Record<string, unknown>): boolean {
    if (node.type !== 'paragraph') return false
    const content = Array.isArray(node.content) ? node.content as Record<string, unknown>[] : []
    if (!content.length) return true
    return content.every((n) => n.type === 'text' && !String(n.text ?? '').trim())
}

export function buildLayoutSegments(layoutDocument: unknown): LayoutSegment[] | null {
    if (!layoutDocument || typeof layoutDocument !== 'object') return null

    const doc = layoutDocument as { type?: string; content?: unknown[] }
    if (doc.type !== 'doc' || !Array.isArray(doc.content)) return null

    const segments: LayoutSegment[] = []
    let textBuffer: Record<string, unknown>[] = []
    let textKey = 0

    function flushText() {
        if (textBuffer.length && textBuffer.some((n) => !isEmptyParagraph(n))) {
            segments.push({type: 'text', key: `text-${textKey++}`, document: {type: 'doc', content: textBuffer}})
        }
        textBuffer = []
    }

    for (const node of doc.content) {
        const n = node as Record<string, unknown>
        if (n.type === 'scenarioField') {
            flushText()
            const blockId = String((n.attrs as Record<string, unknown> | undefined)?.fieldId ?? '')
            if (blockId) segments.push({type: 'field', key: `field-${blockId}`, blockId})
            continue
        }
        textBuffer.push(n)
    }
    flushText()

    return segments
}
