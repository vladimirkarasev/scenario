import {useSectionTree} from '@/composables/useSectionTree'
import {actionCategoryRepository} from '@/modules/actions/repositories/actionCategoryRepository'
import type {ActionCategory} from '@/modules/actions/types/action'

export function useActionSectionTree() {
    return useSectionTree<ActionCategory>({
        loadByParent: parentId => actionCategoryRepository.byParent(parentId),
        allLabel: 'Все действия',
    })
}
