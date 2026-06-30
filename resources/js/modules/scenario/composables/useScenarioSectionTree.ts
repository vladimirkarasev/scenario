import {useSectionTree} from '@/composables/useSectionTree'
import {scenarioRepository} from '@/modules/scenario/repositories/scenarioRepository'
import type {CategoryRef} from '@/modules/scenario/repositories/categoryRepository'
import type {SectionNode} from '@/types/section'

export type ScenarioSectionNode = SectionNode<CategoryRef>

export function useScenarioSectionTree() {
    return useSectionTree<CategoryRef>({
        loadByParent: parentId => scenarioRepository.categoriesByParent(parentId),
        allLabel: 'Сценарии',
    })
}
