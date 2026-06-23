import {reactive, ref} from 'vue'
import {toast} from 'vue-sonner'
import {directoryRepository} from '@/modules/directories/repositories/directoryRepository'
import {defaultOperator} from '@/modules/directories/types/directory'
import type {
    Directory,
    DirectorySchemaField,
    FieldType,
    FilterDisplayType,
    FilterOperator,
    SourceType
} from '@/modules/directories/types/directory'

export interface DirectoryMeta {
    name: string
    slug: string
    description: string | null
    source_type: SourceType
    match_by: string | null
    default_sort: string | null
    proxy_uuid: string | null
    field_mapping: Record<string, string>
    fields: DirectorySchemaField[]
    category_ids: string[]
}

function emptyMeta(): DirectoryMeta {
    return {
        name: '',
        slug: '',
        description: null,
        source_type: 'manual',
        match_by: null,
        default_sort: null,
        proxy_uuid: null,
        field_mapping: {},
        fields: [],
        category_ids: [],
    }
}

export function useDirectoryDetail(directoryId: string) {
    const directory = ref<Directory | null>(null)
    const loading = ref(false)
    const saving = ref(false)
    const saveError = ref<string | null>(null)

    const meta = reactive<DirectoryMeta>(emptyMeta())

    function syncMetaFromDirectory(d: Directory): void {
        const fields = d.active_version?.schema_json ?? d.latest_version?.schema_json ?? []
        meta.name = d.name
        meta.slug = d.slug
        meta.description = d.description
        meta.source_type = d.source_type
        meta.match_by = d.match_by
        meta.default_sort = d.default_sort
        meta.proxy_uuid = (d as unknown as {
            api_config?: Record<string, unknown>
        }).api_config?.proxy_uuid as string | null ?? null
        meta.field_mapping = (d as unknown as {
            api_config?: Record<string, unknown>
        }).api_config?.field_mapping as Record<string, string> ?? {}
        meta.category_ids = d.category_ids ?? []
        meta.fields = fields.length
            ? fields.map(f => {
                const type = ((f as unknown as { type?: string }).type ?? 'string') as FieldType
                const filterType = ((f as unknown as { filter_type?: string }).filter_type ?? type) as FilterDisplayType
                return {
                    key: f.key,
                    name: f.name,
                    type,
                    filter_type: filterType,
                    filter_multiple: (f as unknown as { filter_multiple?: boolean }).filter_multiple ?? false,
                    nullable: (f as unknown as { nullable?: boolean }).nullable ?? true,
                    filterable: (f as unknown as { filterable?: boolean }).filterable ?? false,
                    searchable: (f as unknown as { searchable?: boolean }).searchable ?? false,
                    filter_operator: ((f as unknown as {
                        filter_operator?: string
                    }).filter_operator ?? defaultOperator(filterType)) as FilterOperator,
                    filter_placeholder: (f as unknown as {
                        filter_placeholder?: string | null
                    }).filter_placeholder ?? null,
                    default: null,
                    sort_order: (f as unknown as { sort_order?: number }).sort_order ?? 0,
                    rules: [],
                    options: (f as unknown as { options?: string[] }).options ?? [],
                }
            })
            : [{
                key: '',
                name: '',
                type: 'string' as FieldType,
                filter_type: 'string' as FilterDisplayType,
                filter_multiple: false,
                nullable: true,
                filterable: false,
                searchable: false,
                filter_operator: 'contains' as FilterOperator,
                filter_placeholder: null,
                default: null,
                sort_order: 0,
                rules: [],
                options: []
            }]
    }

    async function load(): Promise<void> {
        loading.value = true
        try {
            const result = await directoryRepository.find(directoryId)
            directory.value = result.item
            syncMetaFromDirectory(result.item)
        } finally {
            loading.value = false
        }
    }

    async function save(): Promise<void> {
        saving.value = true
        saveError.value = null
        try {
            const result = await directoryRepository.update(directoryId, {
                name: meta.name,
                slug: meta.slug,
                description: meta.description,
                category_ids: meta.category_ids,
                source_type: meta.source_type,
                match_by: meta.match_by,
                fields: meta.fields.filter(f => f.key),
                api_config: (meta.source_type === 'api' || meta.source_type === 'external') ? {
                    proxy_uuid: meta.proxy_uuid,
                    field_mapping: meta.field_mapping,
                } : null,
            })
            directory.value = result.item
            syncMetaFromDirectory(result.item)
            flash('Справочник обновлён')
        } catch (e: unknown) {
            saveError.value = e instanceof Error ? e.message : 'Ошибка сохранения.'
            toast.error(saveError.value)
        } finally {
            saving.value = false
        }
    }

    function flash(msg: string): void {
        toast.success(msg)
    }

    return {directory, loading, saving, saveError, meta, load, save, flash}
}

export type DirectoryDetailContext = ReturnType<typeof useDirectoryDetail>
