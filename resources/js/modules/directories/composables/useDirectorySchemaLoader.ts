import { ref, watch, onMounted, type Ref } from 'vue'
import { directoryRepository } from '@/modules/directories/repositories/directoryRepository'
import type { DirectorySchemaField, DirectoryVersion } from '@/modules/directories/types/directory'

export function useDirectorySchemaLoader(
    directoryIdRef: Ref<string>,
    versionIdRef: Ref<string>,
    onAutoVersion: (id: string) => void,
) {
    const directoryName = ref('')
    const versions      = ref<DirectoryVersion[]>([])
    const schemaFields  = ref<DirectorySchemaField[]>([])

    function versionLabel(v: DirectoryVersion): string {
        const base = v.code ? `${v.code} (v${v.version_number})` : `v${v.version_number}`
        return v.is_active ? `${base} — активная` : base
    }

    function schemaForVersionId(id: string): DirectorySchemaField[] {
        return versions.value.find((v) => String(v.id) === id)?.schema_json ?? []
    }

    async function loadDirectory(id: string): Promise<void> {
        try {
            const [dirRes, verRes] = await Promise.all([
                directoryRepository.find(id),
                directoryRepository.versions(id),
            ])
            directoryName.value = dirRes.item.name
            versions.value      = verRes.items
            schemaFields.value  = schemaForVersionId(versionIdRef.value)

            if (!versionIdRef.value) {
                const active = verRes.items.find((v) => v.is_active)
                if (active) onAutoVersion(String(active.id))
            }
        } catch { /* silent */ }
    }

    onMounted(() => {
        if (directoryIdRef.value) void loadDirectory(directoryIdRef.value)
    })

    watch(directoryIdRef, (id) => {
        if (id) void loadDirectory(id)
        else { directoryName.value = ''; versions.value = []; schemaFields.value = [] }
    })

    watch(versionIdRef, (id) => {
        schemaFields.value = schemaForVersionId(id)
    })

    return { directoryName, versions, schemaFields, versionLabel }
}
