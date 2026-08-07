import type {BlockField} from '@/modules/scenario/lib/scenario-block-fields'

type OptionalBlockField = BlockField | null | undefined

/**
 * Checks a field variable name against the other available fields.
 */
export function isBlockFieldVarNameUnique(
    selectedField: OptionalBlockField,
    fields: readonly OptionalBlockField[],
): boolean {
    if (!selectedField?.varName) return true

    const {id, varName} = selectedField

    return !fields.some((field) => field?.id !== id && field?.varName === varName)
}
