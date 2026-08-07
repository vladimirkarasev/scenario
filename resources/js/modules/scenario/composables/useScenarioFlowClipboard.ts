import {onBeforeUnmount, onMounted, ref, type ComputedRef, type Ref} from 'vue'
import {toast} from 'vue-sonner'
import {
    duplicateScenarioFlowBlocks,
    parseScenarioFlowClipboard,
    serializeScenarioFlowClipboard,
    toVueFlowState,
    type ScenarioBlock,
    type ScenarioConnection,
} from '@/modules/scenario/lib/scenario-flow-document'
import {useNodeClipboard} from '@/modules/scenario/composables/useNodeClipboard'
import {isProgrammaticCopyActive} from '@/lib/clipboard'
import type {
    ScenarioFlowEdge,
    ScenarioFlowNode,
    ScenarioFlowScenario,
} from '@/modules/scenario/types/scenario-flow-editor'
import type {Viewport} from '@/modules/scenario/lib/scenario-flow-document'

interface ScenarioFlowClipboardOptions {
    nodes: Ref<ScenarioFlowNode[]>
    edges: Ref<ScenarioFlowEdge[]>
    viewport: Ref<Viewport>
    selectedNodes: ComputedRef<ScenarioFlowNode[]>
    selectedNode: ComputedRef<ScenarioFlowNode | null>
    selectedEdge: ComputedRef<ScenarioFlowEdge | null>
    scenarios: () => ScenarioFlowScenario[]
    editable: () => boolean
    deleteSelected: () => void
    onChanged: () => void
}

interface ClipboardPayload {
    blocks: ScenarioBlock[]
    connections: ScenarioConnection[]
}

export function useScenarioFlowClipboard(options: ScenarioFlowClipboardOptions) {
    const {copyToClipboard, readFromClipboard, rememberClipboard} = useNodeClipboard()
    const pasteCascade = ref(0)

    function selectedPayload(): ClipboardPayload | null {
        if (!options.selectedNodes.value.length) {
            return null
        }

        const nodeIds = new Set(options.selectedNodes.value.map((node) => node.id))
        const blocks = options.selectedNodes.value.map((node) => ({
            id: node.id,
            type: node.type ?? 'block',
            position: node.position,
            data: node.data,
        }))
        const connections = options.edges.value
            .filter((edge) => nodeIds.has(edge.source) && nodeIds.has(edge.target))
            .map((edge) => ({
                id: edge.id,
                source: {blockId: edge.source, port: edge.sourceHandle ?? null},
                target: {blockId: edge.target, port: edge.targetHandle ?? null},
                label: edge.label ?? null,
                data: edge.data ?? {},
            }))

        return {blocks, connections}
    }

    function pastePayload(payload: ClipboardPayload | null): boolean {
        if (!payload || !payload.blocks.length) {
            return false
        }

        pasteCascade.value += 1

        const {blocks, connections} = duplicateScenarioFlowBlocks(
            payload.blocks,
            payload.connections,
            {x: 48 * pasteCascade.value, y: 48 * pasteCascade.value},
        )
        const pastedState = toVueFlowState({
            format: 'scenario-flow',
            version: 1,
            viewport: options.viewport.value,
            blocks,
            connections,
        }, options.scenarios())

        options.nodes.value = [
            ...options.nodes.value.map((node) => ({...node, selected: false})),
            ...pastedState.nodes.map((node) => ({...node, selected: true})),
        ]
        options.edges.value = [
            ...options.edges.value.map((edge) => ({...edge, selected: false})),
            ...pastedState.edges,
        ]
        options.onChanged()

        toast.success('Вставлено')

        return true
    }

    async function copySelectedNodes(): Promise<void> {
        const payload = selectedPayload()

        if (!payload) {
            return
        }

        pasteCascade.value = 0

        if (await copyToClipboard(payload.blocks, payload.connections)) {
            toast.success('Скопировано')
        }
    }

    async function pasteClipboardNodes(): Promise<void> {
        if (options.editable()) {
            pastePayload(await readFromClipboard())
        }
    }

    function isTextEntryTarget(target: EventTarget | null): boolean {
        return target instanceof HTMLElement
            && (
                ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName)
                || target.isContentEditable
            )
    }

    function handleCopy(event: ClipboardEvent): void {
        if (isProgrammaticCopyActive() || isTextEntryTarget(event.target)) {
            return
        }

        const payload = selectedPayload()

        if (!payload || !event.clipboardData) {
            return
        }

        event.clipboardData.setData(
            'text/plain',
            serializeScenarioFlowClipboard(payload.blocks, payload.connections),
        )
        event.preventDefault()
        pasteCascade.value = 0
        rememberClipboard(payload.blocks, payload.connections)
        toast.success('Скопировано')
    }

    function handlePaste(event: ClipboardEvent): void {
        if (!options.editable() || isTextEntryTarget(event.target)) {
            return
        }

        const payload = parseScenarioFlowClipboard(event.clipboardData?.getData('text/plain') ?? '')

        if (!payload) {
            return
        }

        event.preventDefault()
        pastePayload(payload)
    }

    function handleKeyboardShortcut(event: KeyboardEvent): void {
        const target = event.target

        if (isTextEntryTarget(target)) {
            return
        }

        if (event.key === 'Delete' || event.key === 'Backspace') {
            if (!options.selectedNode.value && !options.selectedEdge.value) {
                return
            }

            event.preventDefault()
            options.deleteSelected()
        }
    }

    onMounted(() => {
        window.addEventListener('keydown', handleKeyboardShortcut)
        window.addEventListener('copy', handleCopy)
        window.addEventListener('paste', handlePaste)
    })

    onBeforeUnmount(() => {
        window.removeEventListener('keydown', handleKeyboardShortcut)
        window.removeEventListener('copy', handleCopy)
        window.removeEventListener('paste', handlePaste)
    })

    return {copySelectedNodes, pasteClipboardNodes}
}
