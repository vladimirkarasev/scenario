import {useSectionTree} from '@/composables/useSectionTree'
import {proxyCategoryRepository} from '@/modules/proxy/repositories/proxyCategoryRepository'
import type {ProxyCategory} from '@/modules/proxy/types/webhook'

export function useProxySectionTree() {
    return useSectionTree<ProxyCategory>({
        loadByParent: parentId => proxyCategoryRepository.byParent(parentId),
        allLabel: 'Все интеграции',
    })
}
