import {ref} from 'vue'
import {directoryRepository} from '@/modules/directories/repositories/directoryRepository'
import type {DirectorySchemaField} from '@/modules/directories/types/directory'

export function useRelatedDirectoryOptions() {
    const targetDirectoryName = ref('')
    const targetSchemaFields = ref<DirectorySchemaField[]>([])
    const targetSchemaLoading = ref(false)

    async function loadTargetSchema(directoryId: string): Promise<void> {
        if (!directoryId) {
            targetDirectoryName.value = ''
            targetSchemaFields.value = []
            return
        }
        targetSchemaLoading.value = true
        try {
            const result = await directoryRepository.find(directoryId)
            targetDirectoryName.value = result.item.name
            targetSchemaFields.value = result.item.active_version?.schema_json
                ?? result.item.latest_version?.schema_json
                ?? []
        } finally {
            targetSchemaLoading.value = false
        }
    }

    return {
        targetDirectoryName,
        targetSchemaFields,
        targetSchemaLoading,
        loadTargetSchema,
    }
}
