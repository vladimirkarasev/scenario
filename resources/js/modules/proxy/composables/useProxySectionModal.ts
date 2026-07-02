import {useSectionModal} from '@/composables/useSectionModal'
import {proxyCategoryRepository} from '@/modules/proxy/repositories/proxyCategoryRepository'
import type {ProxyCategory} from '@/modules/proxy/types/webhook'
import {proxySectionSchema} from '@/modules/proxy/schemas/proxySectionSchema'

export function useProxySectionModal(
    onCreated: (category: ProxyCategory) => void,
    onUpdated: (category: ProxyCategory) => void,
) {
    return useSectionModal<ProxyCategory, typeof proxySectionSchema>(
        {
            schema: proxySectionSchema,
            defaults: {name: '', parent_id: null},
            create: payload => proxyCategoryRepository.create(payload),
            update: (id, payload) => proxyCategoryRepository.update(id, payload),
        },
        onCreated,
        onUpdated,
    )
}
