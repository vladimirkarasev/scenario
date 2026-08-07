import type {BlockField, BlockFieldType} from '@/modules/scenario/lib/scenario-block-fields'

export interface ScenarioFieldPreset {
    id: string
    name: string
    field_type: BlockFieldType
    field: Omit<BlockField, 'id'> & {id?: string}
    schema_version: number
    created_at: string | null
    updated_at: string | null
}

export interface ScenarioFieldPresetPayload {
    name: string
    field: BlockField
}
