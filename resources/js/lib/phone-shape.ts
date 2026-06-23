/**
 * Структура значения телефонного поля, которую отправляет PhoneInput на сабмите.
 * Соответствует PHP-классу Module\Scenario\Support\PhoneShape.
 *   - country:   ISO-код страны ("RU")
 *   - formatted: маскированное представление ("+7 (999) 999-99-99")
 *   - original:  только цифры ("79999999999")
 */
export interface PhoneShape {
    country: string
    formatted: string
    original: string
}

export function isPhoneShape(value: unknown): value is PhoneShape {
    if (!value || typeof value !== 'object') return false
    const v = value as Record<string, unknown>
    return typeof v.country === 'string'
        && typeof v.formatted === 'string'
        && typeof v.original === 'string'
}

export function phoneFormatted(value: unknown): string | null {
    return isPhoneShape(value) ? value.formatted : null
}

export function phoneOriginal(value: unknown): string | null {
    return isPhoneShape(value) ? value.original : null
}

export function phoneCountry(value: unknown): string | null {
    return isPhoneShape(value) ? value.country : null
}
