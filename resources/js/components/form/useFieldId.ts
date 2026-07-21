import {computed, type ComputedRef} from 'vue'

function randomString(): string {
    return Math.random().toString(36).slice(2, 8)
}

export function useFieldId(
    providedId: () => string | undefined,
    providedName?: () => string | undefined,
): ComputedRef<string> {
    const base = providedName?.() || 'field'
    const generated = `${base}-${randomString()}`
    return computed(() => providedId() || generated)
}
