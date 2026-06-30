import {useSectionModal} from '@/composables/useSectionModal'
import {actionCategoryRepository} from '@/modules/actions/repositories/actionCategoryRepository'
import type {ActionCategory} from '@/modules/actions/types/action'
import {actionSectionSchema} from '@/modules/actions/schemas/actionSectionSchema'

export function useActionSectionModal(
    onCreated: (category: ActionCategory) => void,
    onUpdated: (category: ActionCategory) => void,
) {
    return useSectionModal<ActionCategory, typeof actionSectionSchema>(
        {
            schema: actionSectionSchema,
            defaults: {name: '', parent_id: null},
            create: payload => actionCategoryRepository.create(payload),
            update: (id, payload) => actionCategoryRepository.update(id, payload),
        },
        onCreated,
        onUpdated,
    )
}
