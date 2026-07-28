import {ref, watch, onMounted, type Ref} from 'vue'
import {directoryRepository} from '@/modules/directories/repositories/directoryRepository'
import type {DirectorySchemaField, DirectoryVersion} from '@/modules/directories/types/directory'

export function useDirectorySchemaLoader(
    directoryIdRef: Ref<string>,
    versionIdRef: Ref<string>,
    onAutoVersion: (id: string) => void,
) {
    const directoryName = ref('')
    const versions = ref<DirectoryVersion[]>([])
    const schemaFields = ref<DirectorySchemaField[]>([])

    function versionLabel(v: DirectoryVersion): string {
        const base = v.code ? `${v.code} (v${v.version_number})` : `v${v.version_number}`
        return v.is_active ? `${base} — активная` : base
    }

    function schemaForVersionId(id: string): DirectorySchemaField[] {
        return versions.value.find((v) => String(v.id) === id)?.schema_json ?? []
    }

    // Для каждого поля типа related_directory подмешивает поля целевого справочника
    // (ключ "field.key.targetKey") — пригодится, если понадобится не заранее заданный
    // шаблон, а конкретное поле цели напрямую. Только 1 уровень — поля цели, сами
    // являющиеся related_directory, не разворачиваются.
    async function enrichWithRelatedFields(fields: DirectorySchemaField[]): Promise<DirectorySchemaField[]> {
        const relatedFields = fields.filter((f) => f.type === 'related_directory' && f.related_directory_id)
        if (!relatedFields.length) return fields

        const targets = await Promise.all(relatedFields.map(async (field) => {
            try {
                const res = await directoryRepository.find(field.related_directory_id as string)
                const targetSchema = res.item.active_version?.schema_json ?? res.item.latest_version?.schema_json ?? []
                return {field, targetSchema}
            } catch {
                return {field, targetSchema: [] as DirectorySchemaField[]}
            }
        }))

        const extra: DirectorySchemaField[] = []
        for (const {field, targetSchema} of targets) {
            for (const targetField of targetSchema) {
                if (targetField.type === 'related_directory') continue
                extra.push({
                    ...targetField,
                    key: `${field.key}.${targetField.key}`,
                    name: `${field.name} → ${targetField.name}`,
                })
            }
        }

        return [...fields, ...extra]
    }

    async function loadDirectory(id: string): Promise<void> {
        try {
            const [dirRes, verRes] = await Promise.all([
                directoryRepository.find(id),
                directoryRepository.versions(id),
            ])
            directoryName.value = dirRes.item.name
            versions.value = verRes.items
            schemaFields.value = await enrichWithRelatedFields(schemaForVersionId(versionIdRef.value))

            if (!versionIdRef.value) {
                const active = verRes.items.find((v) => v.is_active)
                if (active) onAutoVersion(String(active.id))
            }
        } catch { /* silent */
        }
    }

    onMounted(() => {
        if (directoryIdRef.value) void loadDirectory(directoryIdRef.value)
    })

    watch(directoryIdRef, (id) => {
        if (id) void loadDirectory(id)
        else {
            directoryName.value = '';
            versions.value = [];
            schemaFields.value = []
        }
    })

    watch(versionIdRef, async (id) => {
        schemaFields.value = await enrichWithRelatedFields(schemaForVersionId(id))
    })

    return {directoryName, versions, schemaFields, versionLabel}
}
