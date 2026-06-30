import {useSectionTree} from '@/composables/useSectionTree'
import {categoryRepository, type CategoryRef} from '@/modules/scenario/repositories/categoryRepository'

export function useDirectorySectionTree() {
    return useSectionTree<CategoryRef>({
        loadByParent: parentId => categoryRepository.list(parentId).then(r => r.items),
        allLabel: 'Справочники',
    })
}
