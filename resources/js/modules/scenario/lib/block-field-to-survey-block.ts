import type {BlockField} from '@/modules/scenario/lib/scenario-block-fields'
import type {SurveyBlock} from '@/modules/scenario/lib/scenario-player-types'

function buildSurveyBlock(field: BlockField): SurveyBlock {
    const base = {id: field.id, type: field.type as SurveyBlock['type']}

    switch (field.type) {
        case 'rich_text':
            return {
                ...base,
                props: {
                    document: typeof field.value === 'object' ? field.value : null,
                    html: typeof field.value === 'string' ? field.value : ''
                }
            }

        case 'collapse':
            return {
                ...base,
                props: {
                    label: field.label,
                    document: typeof field.value === 'object' ? field.value : null,
                    html: typeof field.value === 'string' ? field.value : '',
                    defaultCollapsed: field.defaultCollapsed
                }
            }

        case 'checkbox':
            return {
                ...base,
                props: {name: field.name, label: field.label, required: field.required, defaultValue: field.checked}
            }

        case 'select':
            return {
                ...base,
                props: {
                    name: field.name,
                    label: field.label,
                    required: field.required,
                    multiple: field.multiple,
                    options: field.options,
                    allowRootSelection: field.allowRootSelection,
                    defaultSearch: field.defaultSearch,
                    defaultValue: field.value
                }
            }

        case 'textarea':
            return {
                ...base,
                props: {
                    name: field.name,
                    label: field.label,
                    required: field.required,
                    placeholder: field.placeholder,
                    defaultValue: field.value,
                    rows: field.rows,
                    maxLength: field.maxLength
                }
            }

        case 'directory_list':
            return {
                ...base,
                props: {
                    name: field.name,
                    label: field.label,
                    required: field.required,
                    directoryId: field.directoryId,
                    versionId: field.versionId,
                    labelTemplate: field.labelTemplate,
                    multiple: field.multiple,
                    allowRootSelection: field.allowRootSelection,
                    fields: field.fields,
                    defaultSearch: field.defaultSearch,
                    depDrop: field.depDrop ?? null,
                }
            }

        case 'directory_table':
            return {
                ...base,
                props: {
                    name: field.name,
                    label: field.label,
                    required: field.required,
                    directoryId: field.directoryId,
                    versionId: field.versionId,
                    labelTemplate: field.labelTemplate,
                    allowSelection: field.allowSelection,
                    multiple: field.multiple,
                    fields: field.fields,
                    defaultSearch: field.defaultSearch
                }
            }

        case 'suggest':
            return {
                ...base,
                props: {
                    name: field.name,
                    label: field.label,
                    required: field.required,
                    proxyUuid: field.proxyUuid,
                    fields: field.fields,
                    labelTemplate: field.labelTemplate,
                    placeholder: field.placeholder,
                    count: field.count
                }
            }

        case 'hidden':
            return {...base, props: {name: field.name, defaultValue: field.value}}

        case 'number':
            return {
                ...base,
                props: {
                    name: field.name,
                    label: field.label,
                    required: field.required,
                    placeholder: field.placeholder,
                    defaultValue: field.value !== null ? String(field.value) : '',
                    min: field.min,
                    max: field.max,
                    step: field.step,
                    decimalPlaces: field.decimalPlaces
                }
            }

        case 'date':
        case 'datetime':
            return {
                ...base,
                props: {
                    name: field.name,
                    label: field.label,
                    required: field.required,
                    defaultValue: field.value,
                    format: field.format
                }
            }

        case 'map_point':
            return {
                ...base,
                props: {
                    name: field.name,
                    label: field.label,
                    required: field.required,
                    lat: field.lat,
                    lng: field.lng,
                    address: field.address,
                    defaultZoom: field.defaultZoom
                }
            }

        case 'route':
            return {
                ...base,
                props: {
                    name: field.name,
                    label: field.label,
                    required: field.required,
                    routingMode: field.routingMode,
                    showAlternatives: field.showAlternatives,
                    maxWaypoints: field.maxWaypoints
                }
            }

        case 'directory_map':
            return {
                ...base,
                props: {
                    name: field.name,
                    label: field.label,
                    directoryId: field.directoryId,
                    versionId: field.versionId,
                    latKey: field.latKey,
                    lngKey: field.lngKey,
                    detailDocument: field.detailDocument,
                    defaultZoom: field.defaultZoom
                }
            }

        default:
            return {
                ...base,
                props: {
                    name: field.name,
                    label: field.label,
                    required: field.required,
                    placeholder: (field as { placeholder?: string }).placeholder ?? '',
                    defaultValue: (field as { value?: unknown }).value ?? ''
                }
            }
    }
}

export function blockFieldToSurveyBlock(field: BlockField): SurveyBlock {
    let block = buildSurveyBlock(field)

    if (block.props && 'label' in block.props) {
        const labelStyle: Record<string, unknown> = {hideLabel: Boolean(field.hideLabel)}
        if (field.labelFontSize) labelStyle.labelFontSize = field.labelFontSize
        if (field.labelColor) labelStyle.labelColor = field.labelColor
        if (field.labelHighlight) labelStyle.labelHighlight = field.labelHighlight
        if (Object.keys(labelStyle).length) {
            block = {...block, props: {...block.props, ...labelStyle}}
        }
    }

    if (field.validation?.length) {
        block = {...block, props: {...block.props, validation: field.validation}}
    }

    return block
}

export function blockFieldsToSurveyBlocks(fields: BlockField[]): SurveyBlock[] {
    return fields
        .filter((f) => f.type !== 'action' && f.type !== 'action_list')
        .map(blockFieldToSurveyBlock)
}
