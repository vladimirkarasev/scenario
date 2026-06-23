import { computed, type ComputedRef } from 'vue'

function randomString(): string {
    return Math.random().toString(36).slice(2, 8)
}

/**
 * Возвращает стабильный id для form-контрола.
 *
 * - Если передан явный `id` через props — используется он.
 * - Иначе генерируется `{name}-{random}` (или `field-{random}` если name пуст).
 *
 * Генерация происходит один раз на инстанс компонента — повторные вызовы
 * computed возвращают одно и то же значение, пока props.id не появится.
 */
export function useFieldId(
    providedId: () => string | undefined,
    providedName?: () => string | undefined,
): ComputedRef<string> {
    const base = providedName?.() || 'field'
    const generated = `${base}-${randomString()}`
    return computed(() => providedId() || generated)
}
