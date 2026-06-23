export type SourceType = 'manual' | 'excel' | 'api' | 'external'
export type SyncStatus = 'idle' | 'syncing' | 'error' | 'pending'

export type FieldType = 'string' | 'integer' | 'boolean' | 'date' | 'datetime'

export type FilterDisplayType = FieldType | 'list'

export type FilterOperator =
    | 'contains' | 'starts_with' | 'equals'   // string
    | 'gt' | 'lt' | 'between'                  // integer
    | 'before' | 'after'                       // date / datetime (reuse between too)

export const FILTER_OPERATORS: Record<FilterDisplayType, { value: FilterOperator; label: string }[]> = {
    string: [{value: 'contains', label: 'Содержит'},
        {value: 'equals', label: 'Равно'},
        {value: 'starts_with', label: 'Начинается с'}],
    integer: [{value: 'equals', label: 'Равно'},
        {value: 'gt', label: 'Больше'},
        {value: 'lt', label: 'Меньше'},
        {value: 'between', label: 'Диапазон'}],
    date: [{value: 'equals', label: 'Равно'},
        {value: 'before', label: 'До'},
        {value: 'after', label: 'После'},
        {value: 'between', label: 'Диапазон'}],
    datetime: [{value: 'equals', label: 'Равно'},
        {value: 'before', label: 'До'},
        {value: 'after', label: 'После'},
        {value: 'between', label: 'Диапазон'}],
    boolean: [],
    list: [],
}

export function defaultOperator(filterType: FilterDisplayType): FilterOperator {
    return filterType === 'string' ? 'contains' : 'equals'
}

export interface DirectorySchemaField {
    key: string
    name: string
    type: FieldType
    filter_type: FilterDisplayType
    filter_multiple: boolean
    nullable: boolean
    filterable: boolean
    searchable: boolean
    filter_operator: FilterOperator
    filter_placeholder: string | null
    default: string | null
    sort_order: number
    rules: string[]
    options: string[]
}

export interface DirectoryVersionSyncOptions {
    add_new: boolean
    update_existing: boolean
    delete_unused: boolean
}

export interface DirectoryOtherOptionSettings {
    allow_other: boolean
    other_label: string | null
    other_external_key?: string | null
}

export interface DirectoryVersion {
    id: number
    version_number: number
    code: string | null
    status: string
    is_active: boolean
    source_type: SourceType
    sync_options: DirectoryVersionSyncOptions | null
    allow_other: boolean
    other_label: string | null
    other_external_key: string | null
    schema_json: DirectorySchemaField[]
    items_count?: number | null
    imports_count?: number | null
    created_at: string | null
}

export interface DirectoryImport {
    id: number
    directory_id: string
    directory_version_id: number | null
    version_number: number | null
    mode: string
    status: string
    source_type: string
    source_label: string
    match_by: string | null
    parent_key_field: string | null
    chunk_size: number | null
    remote_url: string | null
    processed_rows: number
    imported_rows: number
    failed_rows: number
    error_message: string | null
    started_at: string | null
    finished_at: string | null
    created_at: string | null
}

export interface DirectoryImportSchedule {
    enabled: boolean
    frequency: string | null
    run_at: string | null
    day_of_week: number | null
    day_of_month: number | null
    timezone: string
    mode?: string | null
    match_by?: string | null
    chunk_size?: number | null
    mapping_json?: Record<string, string> | null
    remote_config_json?: Record<string, unknown> | null
    next_run_at?: string | null
}

export interface Directory {
    id: string
    project_id: string
    category_ids: string[]
    name: string
    slug: string
    description: string | null
    source_type: SourceType
    match_by: string | null
    default_sort: string | null
    last_sync_at: string | null
    next_sync_at: string | null
    sync_status: SyncStatus
    sync_error: string | null
    versions_count: number
    versions?: DirectoryVersion[]
    active_version: DirectoryVersion | null
    latest_version: (Pick<DirectoryVersion, 'id' | 'version_number' | 'status' | 'is_active' | 'schema_json' | 'created_at'>) | null
    sample_items: DirectoryItem[]
    imports: DirectoryImport[]
    import_schedule: DirectoryImportSchedule | null
    import_settings: {
        mode?: string
        chunk_size?: number
        match_by?: string | null
        fields_text?: string
        mapping_text?: string
        add_new?: boolean
        update_existing?: boolean
        delete_unused?: boolean
    }
    created_at: string | null
    updated_at: string | null
}

export interface DirectoryItem {
    id: number
    parent_id: number | null
    external_key: string
    data: Record<string, string | null>
    created_at: string | null
}

export interface DirectoryListMeta {
    current_page: number
    last_page: number
    per_page: number
    total: number
}

export interface DirectoryPayload {
    name: string
    slug: string
    description: string | null
    category_ids: string[]
    source_type: SourceType
    match_by: string | null
    fields: DirectorySchemaField[]
    api_config?: Record<string, unknown> | null
}
