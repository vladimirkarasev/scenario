export interface DirectoryListShape {
    id: string
    label: string
    data: Record<string, string | null>
    parent_id: string | null
    external_key: string
    other_text?: string | null
}

export function isDirectoryListShape(value: unknown): value is DirectoryListShape {
    if (!value || typeof value !== 'object') return false
    const v = value as Record<string, unknown>
    return typeof v.id === 'string'
        && typeof v.label === 'string'
        && typeof v.external_key === 'string'
        && (v.parent_id === null || typeof v.parent_id === 'string')
        && !!v.data && typeof v.data === 'object'
}

export function directoryListId(value: unknown): string | null {
    return isDirectoryListShape(value) ? value.id : null
}

export function directoryListLabel(value: unknown): string | null {
    return isDirectoryListShape(value) ? value.label : null
}

export const OTHER_EXTERNAL_KEY = '__other__'

export const OTHER_ITEM_ID = -1

export function isOtherDirectoryShape(value: unknown, otherKey: string = OTHER_EXTERNAL_KEY): boolean {
    return isDirectoryListShape(value) && value.external_key === otherKey
}

export function directoryOtherText(value: unknown): string | null {
    return isDirectoryListShape(value) ? (value.other_text ?? null) : null
}
