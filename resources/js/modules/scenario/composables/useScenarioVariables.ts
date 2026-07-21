import {computed, type ComputedRef} from 'vue'
import {blocksToVariableEntries} from '@/modules/scenario/lib/scenario-variables'
import type {ScenarioBlock} from '@/modules/scenario/lib/scenario-flow-document'
import type {VariableEntry} from '@/modules/scenario/types/scenario-variable-entry'
import {
    toLinkedScenarioRefs,
    useLinkedScenarioVariables,
    type LinkedBlockEntry,
} from '@/modules/scenario/composables/useLinkedScenarioVariables'

export interface VariableListBlock {
    id: string
    type: string
    data: { title?: string }
}

/**
 * @param getBlocks       Блоки текущего графа (для редактора блока — с подменой
 *                        редактируемого блока на «живой» draft).
 * @param getCurrentBlockId  Id «текущего» блока — помечается бейджем в списке.
 */
export function useScenarioVariables(
    getBlocks: () => ScenarioBlock[],
    getCurrentBlockId: () => string = () => '',
): { variables: ComputedRef<VariableEntry[]>; blocks: ComputedRef<Array<VariableListBlock | LinkedBlockEntry>> } {
    const {linkedEntries, linkedBlocks} = useLinkedScenarioVariables(() => toLinkedScenarioRefs(getBlocks()))

    const variables = computed<VariableEntry[]>(() => [
        ...blocksToVariableEntries(getBlocks(), getCurrentBlockId()),
        ...linkedEntries.value,
    ])

    const blocks = computed<Array<VariableListBlock | LinkedBlockEntry>>(() => [
        ...getBlocks(),
        ...linkedBlocks.value,
    ])

    return {variables, blocks}
}
