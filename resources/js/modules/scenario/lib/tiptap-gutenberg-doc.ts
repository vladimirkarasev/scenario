import type {BlockField} from '@/modules/scenario/lib/scenario-block-fields'

export interface TiptapJsonNode {
    type?: string
    content?: TiptapJsonNode[]
    [key: string]: unknown
}

export function buildScenarioFieldNode(field: BlockField): TiptapJsonNode {
    const marks: TiptapJsonNode[] = []

    if (field.labelFontSize || field.labelColor) {
        marks.push({
            type: 'textStyle',
            attrs: {fontSize: field.labelFontSize ?? null, color: field.labelColor ?? null},
        })
    }

    if (field.labelHighlight) {
        marks.push({type: 'highlight', attrs: {color: field.labelHighlight}})
    }

    return {
        type: 'scenarioField',
        attrs: {fieldId: field.id, hideLabel: Boolean(field.hideLabel)},
        content: field.label
            ? [{type: 'text', text: field.label, ...(marks.length ? {marks} : {})}]
            : [],
    }
}

export function syncScenarioFieldPresentations(
    document: TiptapJsonNode,
    fields: BlockField[],
): TiptapJsonNode {
    if (document.type !== 'doc' || !Array.isArray(document.content)) {
        return document
    }

    const fieldsById = new Map(fields.map((field) => [field.id, field]))
    let changed = false
    const content = document.content.map((value) => {
        if (!value || typeof value !== 'object') {
            return value
        }

        const node = value as TiptapJsonNode

        if (node.type !== 'scenarioField') {
            return node
        }

        const attrs = node.attrs && typeof node.attrs === 'object'
            ? node.attrs as Record<string, unknown>
            : {}
        const field = fieldsById.get(String(attrs.fieldId ?? ''))

        if (!field) {
            return node
        }

        const nextContent = buildScenarioFieldNode(field).content as TiptapJsonNode[]
        const currentContent = Array.isArray(node.content) ? node.content : []
        const nextHideLabel = Boolean(field.hideLabel)
        const currentHideLabel = Boolean(attrs.hideLabel)

        if (JSON.stringify(currentContent) === JSON.stringify(nextContent) && currentHideLabel === nextHideLabel) {
            return node
        }

        changed = true

        return {...node, attrs: {...attrs, fieldId: field.id, hideLabel: nextHideLabel}, content: nextContent}
    })

    return changed ? {...document, content} : document
}

function hasNodeContent(value: unknown): boolean {
    if (!value || typeof value !== 'object') return false

    const node = value as TiptapJsonNode

    if (node.type === 'text') {
        return typeof node.text === 'string' && node.text.trim() !== ''
    }

    if (node.type === 'horizontalRule' || node.type === 'image') {
        return true
    }

    return Array.isArray(node.content)
        && node.content.some(hasNodeContent)
}

export function hasTiptapDocumentContent(value: unknown): boolean {
    if (!value || typeof value !== 'object') return false

    const document = value as TiptapJsonNode

    return document.type === 'doc'
        && Array.isArray(document.content)
        && document.content.some(hasNodeContent)
}

export function tiptapDocumentPlainText(value: unknown): string {
    const fragments: string[] = []

    function visit(nodeValue: unknown): void {
        if (!nodeValue || typeof nodeValue !== 'object') return

        const node = nodeValue as TiptapJsonNode
        if (node.type === 'text' && typeof node.text === 'string') {
            fragments.push(node.text)
        }

        if (Array.isArray(node.content)) {
            node.content.forEach(visit)
        }
    }

    visit(value)

    return fragments.join(' ').replace(/\s+/g, ' ').trim()
}

export function isEmptyParagraphNode(node: TiptapJsonNode | undefined): boolean {
    if (!node || node.type !== 'paragraph') return false
    const content = node.content

    return !Array.isArray(content) || content.length === 0
}

export function insertBeforeTrailingEmptyParagraph(
    content: TiptapJsonNode[],
    nodes: TiptapJsonNode[],
): TiptapJsonNode[] {
    const last = content[content.length - 1]
    if (isEmptyParagraphNode(last)) {
        return [...content.slice(0, -1), ...nodes, last]
    }

    return [...content, ...nodes]
}

export interface TiptapDocSummary {
    contentSize: number
    lastChild: { typeName: string; contentSize: number; nodeSize: number } | null
}

export function computeFieldInsertPosition(doc: TiptapDocSummary): number {
    const {lastChild, contentSize} = doc

    if (lastChild && lastChild.typeName === 'paragraph' && lastChild.contentSize === 0) {
        return contentSize - lastChild.nodeSize
    }

    return contentSize
}
