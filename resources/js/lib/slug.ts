import slugify from 'slugify'

export function toSlug(value: string): string {
    return slugify(value, {lower: true, strict: true, locale: 'ru'})
}

export function isSlug(value: string): boolean {
    return value !== '' && toSlug(value) === value
}
