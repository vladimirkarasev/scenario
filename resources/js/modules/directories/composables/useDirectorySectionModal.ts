import {useSectionModal} from '@/composables/useSectionModal'
import {categoryRepository} from '@/modules/scenario/repositories/categoryRepository'
import type {CategoryRef} from '@/modules/scenario/types/category'
import {directorySectionSchema} from '@/modules/directories/schemas/directorySectionSchema'

export function useDirectorySectionModal(
    onCreated: (category: CategoryRef) => void,
    onUpdated: (category: CategoryRef) => void,
) {
    return useSectionModal<CategoryRef, typeof directorySectionSchema>(
        {
            schema: directorySectionSchema,
            defaults: {name: '', parent_id: null},
            create: payload => categoryRepository.create(payload),
            update: (id, payload) => categoryRepository.update(id, payload),
        },
        onCreated,
        onUpdated,
    )
}
