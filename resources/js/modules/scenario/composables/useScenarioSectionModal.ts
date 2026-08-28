import {ref} from 'vue'
import {useSectionModal} from '@/composables/useSectionModal'
import {scenarioCategoryRepository} from '@/modules/scenario/repositories/scenarioCategoryRepository'
import type {CategoryRef, ScenarioCategoryPayload} from '@/modules/scenario/types/category'
import {scenarioSectionSchema} from '@/modules/scenario/schemas/scenarioSectionSchema'

export function useScenarioSectionModal(
    onCreated: (category: CategoryRef) => void,
    onUpdated: (category: CategoryRef) => void,
) {
    const selectedGroups = ref<Array<Record<string, unknown>>>([])

    const modal = useSectionModal<CategoryRef, typeof scenarioSectionSchema, ScenarioCategoryPayload>(
        {
            schema: scenarioSectionSchema,
            defaults: {
                name: '',
                parent_id: null,
                group_ids: [],
                inherit_to_descendants: false,
                is_workspace: false,
            },
            create: payload => scenarioCategoryRepository.create(payload),
            update: (id, payload) => scenarioCategoryRepository.update(id, payload),
            buildPayload: data => ({
                name: data.name.trim(),
                parent_id: data.parent_id,
                is_active: true,
                group_ids: selectedGroups.value.map(g => String(g.id)),
                inherit_to_descendants: data.inherit_to_descendants,
                is_workspace: data.is_workspace,
            }),
            fromCategory: category => ({
                name: category.name,
                parent_id: category.parent_id,
                group_ids: category.group_ids ?? [],
                inherit_to_descendants: false,
                is_workspace: category.is_workspace ?? false,
            }),
            onOpenModal: () => {
                selectedGroups.value = []
            },
            onOpenEdit: (category) => {
                selectedGroups.value = (category.group_ids ?? []).map(id => ({id, name: id}))
            },
        },
        onCreated,
        onUpdated,
    )

    return {...modal, selectedGroups}
}
