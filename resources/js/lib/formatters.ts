const LOCALE = 'ru-RU'

export function formatDateTime(value: string | null | undefined): string {
    if (!value) return '—'
    return new Intl.DateTimeFormat(LOCALE, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value))
}

export function formatDate(value: string | null | undefined): string {
    if (!value) return '—'
    return new Intl.DateTimeFormat(LOCALE, {
        dateStyle: 'medium',
    }).format(new Date(value))
}

export function formatTime(value: string | null | undefined): string {
    if (!value) return '—'
    return new Intl.DateTimeFormat(LOCALE, {
        timeStyle: 'short',
    }).format(new Date(value))
}
