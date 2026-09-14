import {duplicateBlockFieldIds, normalizeScenarioBlockField, type BlockField} from '@/modules/scenario/lib/scenario-block-fields'
import {normalizeConditionAnswerIcon} from '@/modules/scenario/lib/condition-answer-icons'
import {ScenarioContextKey} from '@/modules/scenario/types/scenario-context-key'

export type NodeType = 'start' | 'block' | 'question' | 'action' | 'condition' | 'end' | 'scenario_link'

export function isContentNodeType(type: string): type is 'block' | 'question' {
    return type === 'block' || type === 'question'
}

export interface ConditionBranch {
    id: string
    label: string
    icon: string | null
    condition: string
    action: 'transition' | 'url'
    url: string
    width: 'full' | 'half'
    priority: number
}

export interface ScenarioBlockData {
    title: string
    hideTitle: boolean
    variable: string
    skipInSurvey: boolean
    text: string
    fields: BlockField[]
    layoutDocument?: unknown
    content?: unknown
    targetScenarioId: string | null
    targetVersionId: string | null
    description: string
    conditionBranches: ConditionBranch[]

    [key: string]: unknown
}

export interface ScenarioBlock {
    id: string
    type: NodeType
    position: { x: number; y: number }
    data: ScenarioBlockData
}

export interface ScenarioConnection {
    id: string
    source: { blockId: string; port: string | null }
    target: { blockId: string; port: string | null }
    label: string | null
    data: Record<string, unknown>
}

export interface Viewport {
    x: number
    y: number
    zoom: number
}

export interface ScenarioFlowDocument {
    format: 'scenario-flow'
    version: number
    viewport: Viewport
    blocks: ScenarioBlock[]
    connections: ScenarioConnection[]
}

interface Scenario {
    id: string
    name: string
}

const SCHEMA_FORMAT = 'scenario-flow' as const
const SCHEMA_VERSION = 1

function clone<T>(value: T): T {
    return JSON.parse(JSON.stringify(value)) as T
}

function asRecord(v: unknown): Record<string, unknown> {
    return (v && typeof v === 'object' && !Array.isArray(v)) ? v as Record<string, unknown> : {}
}

function uid(prefix: string): string {
    return `${prefix}_${Math.random().toString(36).slice(2, 10)}`
}

function defaultNodeData(type: string): ScenarioBlockData {
    const byType: Record<string, ScenarioBlockData> = {
        start: {
            title: 'Начало',
            hideTitle: true,
            variable: '',
            skipInSurvey: false,
            text: '',
            fields: [],
            targetScenarioId: null,
            targetVersionId: null,
            description: '',
            conditionBranches: []
        },
        condition: {
            title: 'Условие',
            hideTitle: true,
            variable: '',
            skipInSurvey: false,
            text: '',
            fields: [],
            content: null,
            targetScenarioId: null,
            targetVersionId: null,
            description: '',
            conditionBranches: [],
        },
        block: {
            title: 'Блок',
            hideTitle: true,
            variable: 'Block',
            skipInSurvey: false,
            text: '',
            fields: [],
            targetScenarioId: null,
            targetVersionId: null,
            description: '',
            conditionBranches: []
        },
        question: {
            title: 'Вопрос',
            hideTitle: false,
            variable: 'Question',
            skipInSurvey: false,
            text: '',
            fields: [],
            targetScenarioId: null,
            targetVersionId: null,
            description: '',
            conditionBranches: []
        },
        action: {
            title: 'Действие',
            hideTitle: true,
            variable: '',
            skipInSurvey: false,
            text: '',
            fields: [],
            targetScenarioId: null,
            targetVersionId: null,
            description: '',
            conditionBranches: [],
            execution_mode: 'sequential',
            action_items: [],
            before_action_id: '',
            before_code: '',
            before_input: {},
            error_action_id: '',
            error_code: '',
            error_input: {}
        },
        end: {
            title: 'Конец',
            hideTitle: true,
            variable: '',
            skipInSurvey: false,
            text: '',
            fields: [],
            targetScenarioId: null,
            targetVersionId: null,
            description: `<h3>Опрос завершен</h3> <p>Опрос успешно пройден #{{ ${ScenarioContextKey.Run}.number_formatted }} от {{ ${ScenarioContextKey.Run}.completed_at }}</p>`,
            conditionBranches: []
        },
        scenario_link: {
            title: 'Переход',
            hideTitle: true,
            variable: '',
            skipInSurvey: false,
            text: '',
            fields: [],
            targetScenarioId: null,
            targetVersionId: null,
            description: '',
            conditionBranches: []
        },
    }

    return clone(byType[type] ?? byType.block)
}

function normalizeBlock(block: unknown, index: number): ScenarioBlock {
    const b = block as Record<string, unknown> | null | undefined
    const type = String(b?.type ?? 'block') as NodeType
    const defaultData = defaultNodeData(type)
    const rawData = asRecord(b?.data)
    const rawPosition = asRecord(b?.position)
    const rawBranches: unknown[] = Array.isArray(rawData.conditionBranches) && rawData.conditionBranches.length > 0
        ? rawData.conditionBranches as unknown[]
        : Array.isArray(rawData.options)
            ? rawData.options as unknown[]
            : []
    const conditionBranches = rawBranches.map((branch: unknown, branchIndex: number): ConditionBranch => {
        const br = branch as Record<string, unknown> | null
        const rawPriority = Number(br?.priority ?? branchIndex + 1)

        return {
            id: String(br?.id ?? uid(`condition_branch_${branchIndex}`)),
            label: String(br?.label ?? (branchIndex === 0 ? 'Да' : branchIndex === 1 ? 'Нет' : `Вариант ${branchIndex + 1}`)),
            icon: normalizeConditionAnswerIcon(br?.icon),
            condition: String(br?.condition ?? 'true'),
            action: br?.action === 'url' ? 'url' : 'transition',
            url: String(br?.url ?? ''),
            width: br?.width === 'half' ? 'half' : 'full',
            priority: Number.isInteger(rawPriority) && rawPriority > 0 ? rawPriority : branchIndex + 1,
        }
    })

    return {
        id: String(b?.id ?? uid(`block_${index}`)),
        type,
        position: {
            x: Number(rawPosition.x ?? 120 + index * 40),
            y: Number(rawPosition.y ?? 120 + index * 40),
        },
        data: {
            ...defaultData,
            ...rawData,
            hideTitle: Boolean(rawData.hideTitle ?? true),
            variable: String(rawData.variable ?? (isContentNodeType(type) ? (rawData.title ?? defaultData.title) : '')),
            skipInSurvey: Boolean(rawData.skipInSurvey ?? false),
            fields: Array.isArray(rawData.fields)
                ? (rawData.fields as unknown[]).map(normalizeScenarioBlockField)
                : [],
            targetScenarioId: rawData.targetScenarioId ? String(rawData.targetScenarioId) : null,
            targetVersionId: rawData.targetVersionId ? String(rawData.targetVersionId) : null,
            description: typeof rawData.description === 'string' ? rawData.description : (defaultData.description ?? ''),
            conditionBranches,
            ...(type === 'condition' ? {options: conditionBranches} : {}),
        },
    }
}

function normalizeConnection(connection: unknown, index: number): ScenarioConnection {
    const c = connection as Record<string, unknown> | null | undefined

    const cSource = asRecord(c?.source)
    const cTarget = asRecord(c?.target)

    return {
        id: String(c?.id ?? uid(`connection_${index}`)),
        source: {
            blockId: String(cSource.blockId ?? ''),
            port: cSource.port ? String(cSource.port) : null,
        },
        target: {
            blockId: String(cTarget.blockId ?? ''),
            port: cTarget.port ? String(cTarget.port) : null,
        },
        label: c?.label ? String(c.label) : null,
        data: asRecord(c?.data),
    }
}

function normalizeLegacyNode(node: unknown, index: number): ScenarioBlock {
    const n = node as Record<string, unknown> | null | undefined
    const type = String(n?.type ?? 'block')

    return normalizeBlock({
        id: n?.id ?? uid(`legacy_block_${index}`),
        type,
        position: n?.position,
        data: {...defaultNodeData(type), ...(n?.data ?? {})},
    }, index)
}

function normalizeLegacyEdge(edge: unknown, index: number): ScenarioConnection {
    const e = edge as Record<string, unknown> | null | undefined

    return normalizeConnection({
        id: e?.id ?? uid(`legacy_connection_${index}`),
        source: {blockId: e?.source, port: e?.sourceHandle ?? null},
        target: {blockId: e?.target, port: e?.targetHandle ?? null},
        label: e?.label ?? null,
    }, index)
}

export function createEmptyScenarioFlowDocument(): ScenarioFlowDocument {
    return {
        format: SCHEMA_FORMAT,
        version: SCHEMA_VERSION,
        viewport: {x: 0, y: 0, zoom: 1},
        blocks: [],
        connections: [],
    }
}

export function createScenarioFlowNode(type: NodeType, position = {x: 120, y: 120}): ScenarioBlock {
    return normalizeBlock({id: uid(type), type, position, data: defaultNodeData(type)}, 0)
}

export function normalizeScenarioFlowDocument(value: unknown): ScenarioFlowDocument {
    if (!value || Array.isArray(value)) {
        return createEmptyScenarioFlowDocument()
    }

    const v = value as Record<string, unknown>

    if (Array.isArray(v.nodes) && Array.isArray(v.edges)) {
        const legacyViewport = asRecord(v.viewport)
        const legacyPosition = Array.isArray(v.position) ? v.position as unknown[] : []
        return {
            format: SCHEMA_FORMAT,
            version: SCHEMA_VERSION,
            viewport: {
                x: Number(legacyViewport.x ?? legacyPosition[0] ?? 0),
                y: Number(legacyViewport.y ?? legacyPosition[1] ?? 0),
                zoom: Number(legacyViewport.zoom ?? v.zoom ?? 1),
            },
            blocks: v.nodes.map(normalizeLegacyNode),
            connections: v.edges.map(normalizeLegacyEdge),
        }
    }

    const viewport = asRecord(v.viewport)
    return {
        format: SCHEMA_FORMAT,
        version: Number(v.version ?? SCHEMA_VERSION),
        viewport: {
            x: Number(viewport.x ?? 0),
            y: Number(viewport.y ?? 0),
            zoom: Number(viewport.zoom ?? 1),
        },
        blocks: Array.isArray(v.blocks) ? v.blocks.map(normalizeBlock) : [],
        connections: Array.isArray(v.connections) ? v.connections.map(normalizeConnection) : [],
    }
}

export function toVueFlowState(document: unknown, scenarios: Scenario[] = []) {
    const normalized = normalizeScenarioFlowDocument(document)
    const scenariosById = new Map(scenarios.map((scenario) => [scenario.id, scenario]))
    const nodeTypesById = new Map(normalized.blocks.map((block) => [block.id, block.type]))

    return {
        nodes: normalized.blocks.map((block) => ({
            id: block.id,
            type: block.type,
            position: block.position,
            data: {
                ...block.data,
                targetScenarioName: block.data.targetScenarioId
                    ? scenariosById.get(block.data.targetScenarioId)?.name ?? null
                    : null,
            },
        })),
        edges: normalized.connections
            .filter((connection) => connection.source.blockId && connection.target.blockId)
            .map((connection) => ({
                id: connection.id,
                source: connection.source.blockId,
                sourceHandle: connection.source.port,
                target: connection.target.blockId,
                targetHandle: connection.target.port,
                label: nodeTypesById.get(connection.source.blockId) === 'condition'
                    ? undefined
                    : connection.label ?? undefined,
                data: connection.data,
                type: 'smoothstep',
            })),
        viewport: normalized.viewport,
    }
}

export function fromVueFlowState({nodes, edges, viewport}: {
    nodes: unknown[]
    edges: unknown[]
    viewport: Viewport
}): ScenarioFlowDocument {
    return normalizeScenarioFlowDocument({
        format: SCHEMA_FORMAT,
        version: SCHEMA_VERSION,
        viewport,
        blocks: nodes.map((value) => {
            const node = asRecord(value)

            return {
                id: node.id,
                type: node.type,
                position: node.position,
                data: {...defaultNodeData(String(node.type)), ...asRecord(node.data), targetScenarioName: undefined},
            }
        }),
        connections: edges.map((value) => {
            const edge = asRecord(value)

            return {
                id: edge.id,
                source: {blockId: edge.source, port: edge.sourceHandle ?? null},
                target: {blockId: edge.target, port: edge.targetHandle ?? null},
                label: edge.label ?? null,
                data: edge.data ?? {},
            }
        }),
    })
}

export function cloneScenarioFlowDocument(document: unknown): ScenarioFlowDocument {
    return clone(normalizeScenarioFlowDocument(document))
}

export function stringifyScenarioFlowDocument(document: unknown): string {
    return JSON.stringify(normalizeScenarioFlowDocument(document), null, 2)
}

function duplicateBlockData(data: ScenarioBlockData): ScenarioBlockData {
    const cloned = clone(data)

    if (Array.isArray(cloned.fields)) {
        cloned.fields = duplicateBlockFieldIds(cloned.fields)
    }

    if (Array.isArray(cloned.conditionBranches)) {
        cloned.conditionBranches = cloned.conditionBranches.map((branch) => ({
            ...branch,
            id: uid('condition_branch'),
        }))
        cloned.options = cloned.conditionBranches
    }

    const actionItems = cloned.action_items
    if (Array.isArray(actionItems)) {
        cloned.action_items = actionItems.map((item) => (
            item && typeof item === 'object' ? {...item, id: uid('ali')} : item
        ))
    }

    return cloned
}

export function blockVariableFromId(id: string): string {
    return `block_${id.slice(id.indexOf('_') + 1)}`
}

export function duplicateScenarioFlowBlocks(
    blocks: ScenarioBlock[],
    connections: ScenarioConnection[],
    offset: { x: number; y: number },
): { blocks: ScenarioBlock[]; connections: ScenarioConnection[] } {
    const idMap = new Map<string, string>()
    const conditionBranchIdMaps = new Map<string, Map<string, string>>()

    const duplicatedBlocks = blocks.map((block) => {
        const newId = uid(block.type)
        idMap.set(block.id, newId)
        const data = duplicateBlockData(block.data)

        if (block.type === 'condition') {
            conditionBranchIdMaps.set(block.id, new Map(
                block.data.conditionBranches.map((branch, index) => [
                    branch.id,
                    data.conditionBranches[index]?.id ?? branch.id,
                ]),
            ))
        }

        if (isContentNodeType(block.type)) {
            data.variable = blockVariableFromId(newId)
        }

        return {
            ...block,
            id: newId,
            position: {x: block.position.x + offset.x, y: block.position.y + offset.y},
            data,
        }
    })

    const duplicatedConnections = connections
        .filter((connection) => idMap.has(connection.source.blockId) && idMap.has(connection.target.blockId))
        .map((connection) => ({
            ...clone(connection),
            id: uid('connection'),
            source: {
                ...connection.source,
                blockId: idMap.get(connection.source.blockId)!,
                port: connection.source.port
                    ? conditionBranchIdMaps.get(connection.source.blockId)?.get(connection.source.port)
                    ?? connection.source.port
                    : null,
            },
            target: {...connection.target, blockId: idMap.get(connection.target.blockId)!},
        }))

    return {blocks: duplicatedBlocks, connections: duplicatedConnections}
}

const CLIPBOARD_FORMAT = 'scenario-flow-clipboard' as const

interface ScenarioFlowClipboardPayload {
    format: typeof CLIPBOARD_FORMAT
    version: number
    blocks: ScenarioBlock[]
    connections: ScenarioConnection[]
}

export function serializeScenarioFlowClipboard(blocks: ScenarioBlock[], connections: ScenarioConnection[]): string {
    const payload: ScenarioFlowClipboardPayload = {
        format: CLIPBOARD_FORMAT,
        version: SCHEMA_VERSION,
        blocks: clone(blocks),
        connections: clone(connections),
    }

    return JSON.stringify(payload)
}

export function parseScenarioFlowClipboard(text: string): { blocks: ScenarioBlock[]; connections: ScenarioConnection[] } | null {
    let parsed: unknown

    try {
        parsed = JSON.parse(text)
    } catch {
        return null
    }

    const p = parsed as Record<string, unknown> | null

    if (!p || p.format !== CLIPBOARD_FORMAT || !Array.isArray(p.blocks)) {
        return null
    }

    return {
        blocks: p.blocks.map(normalizeBlock),
        connections: Array.isArray(p.connections) ? p.connections.map(normalizeConnection) : [],
    }
}
