export interface TiptapJsonNode {
    type?: string
    content?: unknown
    [key: string]: unknown
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
