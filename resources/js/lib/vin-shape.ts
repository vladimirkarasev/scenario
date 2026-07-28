export interface VinShape {
    value: string
}

export function isVinShape(value: unknown): value is VinShape {
    if (!value || typeof value !== 'object') return false
    return typeof (value as Record<string, unknown>).value === 'string'
}

export function vinValue(value: unknown): string | null {
    if (isVinShape(value)) return value.value
    return typeof value === 'string' ? value : null
}
