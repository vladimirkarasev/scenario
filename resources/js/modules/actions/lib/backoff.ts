export function parseBackoffInput(value: string): number[] {
    return value
        .split(',')
        .map(part => part.trim())
        .filter(part => part !== '')
        .map(part => Math.max(0, Math.trunc(Number(part)) || 0))
}

export function formatBackoffInput(list: number[] | null | undefined): string {
    return (list ?? []).join(',')
}
