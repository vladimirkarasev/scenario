export interface GrzShape {
    country: string
    formatted: string
    original: string
}

export function isGrzShape(value: unknown): value is GrzShape {
    if (!value || typeof value !== 'object') return false
    const v = value as Record<string, unknown>
    return typeof v.country === 'string'
        && typeof v.formatted === 'string'
        && typeof v.original === 'string'
}

export function grzFormatted(value: unknown): string | null {
    return isGrzShape(value) ? value.formatted : null
}

export function grzOriginal(value: unknown): string | null {
    return isGrzShape(value) ? value.original : null
}

export function grzCountry(value: unknown): string | null {
    return isGrzShape(value) ? value.country : null
}
