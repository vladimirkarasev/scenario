import {buildLayoutSegments} from '@/modules/scenario/lib/block-layout-segments'
import type {BlockField} from '@/modules/scenario/lib/scenario-block-fields'

export interface BlockNodeTextPreviewItem {
    type: 'text'
    key: string
    document: { type: 'doc'; content: Record<string, unknown>[] }
}

export interface BlockNodeFieldPreviewItem {
    type: 'field'
    key: string
    field: BlockField
}

export type BlockNodePreviewItem = BlockNodeTextPreviewItem | BlockNodeFieldPreviewItem

export function buildBlockNodePreviewItems(
    layoutDocument: unknown,
    fields: BlockField[],
): BlockNodePreviewItem[] {
    const layoutSegments = buildLayoutSegments(layoutDocument)

    if (!layoutSegments) {
        return fields.map((field) => ({
            type: 'field',
            key: `field-${field.id}`,
            field,
        }))
    }

    const fieldsById = new Map(fields.map((field) => [field.id, field]))

    return layoutSegments.flatMap((segment): BlockNodePreviewItem[] => {
        if (segment.type === 'text') {
            return [segment]
        }

        const field = fieldsById.get(segment.blockId)

        return field ? [{type: 'field', key: segment.key, field}] : []
    })
}
