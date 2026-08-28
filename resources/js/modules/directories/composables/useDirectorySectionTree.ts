import {useSectionTree} from '@/composables/useSectionTree'
import {categoryRepository} from '@/modules/scenario/repositories/categoryRepository'
import type {CategoryRef} from '@/modules/scenario/types/category'

export function useDirectorySectionTree() {
    return useSectionTree<CategoryRef>({
        loadByParent: parentId => categoryRepository.list(parentId).then(r => r.items),
        allLabel: 'Справочники',
    })
}
