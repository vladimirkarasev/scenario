export interface SelectShape {
    value: string
    label: string
}

export function isSelectShape(value: unknown): value is SelectShape {
    if (!value || typeof value !== 'object') return false
    const v = value as Record<string, unknown>
    return typeof v.value === 'string' && typeof v.label === 'string'
}

export function selectValue(value: unknown): string | null {
    return isSelectShape(value) ? value.value : null
}

export function selectLabel(value: unknown): string | null {
    return isSelectShape(value) ? value.label : null
}
