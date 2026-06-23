/**
 * Структура значения поля directory_list (single — объект, multiple — массив).
 * Соответствует PHP-классу Module\Scenario\Support\DirectoryListShape.
 *
 *   - id:           ID элемента справочника (string)
 *   - label:        отображаемое имя (отрендеренный labelTemplate)
 *   - data:         все поля элемента справочника
 *   - parent_id:    ID родителя или null
 *   - external_key: внешний ключ
 */
export interface DirectoryListShape {
    id: string
    label: string
    data: Record<string, string | null>
    parent_id: string | null
    external_key: string
    /** Свободный текст «уточнения», заданный респондентом для варианта «Другой». */
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

/**
 * external_key по умолчанию для синтетического варианта «Другой».
 * Зеркалит Module\Directories\Services\DirectoryItemService::OTHER_EXTERNAL_KEY.
 */
export const OTHER_EXTERNAL_KEY = '__other__'

/**
 * Сентинел-id синтетического элемента «Другой».
 * Зеркалит Module\Directories\Services\DirectoryItemService::OTHER_ITEM_ID.
 */
export const OTHER_ITEM_ID = -1

/** Является ли выбранное значение вариантом «Другой». */
export function isOtherDirectoryShape(value: unknown, otherKey: string = OTHER_EXTERNAL_KEY): boolean {
    return isDirectoryListShape(value) && value.external_key === otherKey
}

/** Свободный текст «уточнения» из shape «Другой», либо null. */
export function directoryOtherText(value: unknown): string | null {
    return isDirectoryListShape(value) ? (value.other_text ?? null) : null
}
