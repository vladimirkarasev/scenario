import type {BlockField} from '@/modules/scenario/lib/scenario-block-fields'
import type {ScenarioBlock} from '@/modules/scenario/lib/scenario-flow-document'
import type {VariableEntry} from '@/modules/scenario/types/scenario-variable-entry'

const NON_VARIABLE_FIELD_TYPES = new Set(['rich_text', 'collapse', 'action', 'action_list'])

export function fieldsToVariableEntries(
    fields: BlockField[],
    blockId: string,
    blockTitle: string,
    isCurrent: boolean,
): VariableEntry[] {
    return fields.flatMap((f): VariableEntry[] => {
        if (NON_VARIABLE_FIELD_TYPES.has(f.type)) return []
        if (!f.varName) return []
        const base: VariableEntry = {
            fieldId: f.id, blockId, blockTitle,
            varRef: `{{ ${f.varName} }}`,
            label: f.label || f.varName,
            isCurrent, isAccessor: false, accessorOf: null, fieldType: f.type,
        }
        if (f.type === 'directory_list' || f.type === 'directory_table') {
            base.directoryId = f.directoryId
            base.versionId = f.versionId
        }
        if (f.type === 'select') {
            base.options = (f.options ?? []).map((o) => ({value: o.value, label: o.label}))
            base.multiple = f.multiple
        }
        if (f.type === 'suggest') {
            base.proxyUuid = f.proxyUuid
        }
        return [base]
    })
}

export function blocksToVariableEntries(
    blocks: ScenarioBlock[],
    currentBlockId = '',
): VariableEntry[] {
    return blocks
        .filter((b) => b.type === 'block')
        .flatMap((b) => fieldsToVariableEntries(
            b.data.fields ?? [],
            b.id,
            b.data.title || b.id,
            b.id === currentBlockId,
        ))
}
