import {effectScope, nextTick, ref} from 'vue'
import {describe, expect, it, vi} from 'vitest'
import {useScenarioFlowDocument} from '@/modules/scenario/composables/useScenarioFlowDocument'
import {createScenarioFlowNode, type ScenarioFlowDocument} from '@/modules/scenario/lib/scenario-flow-document'
import type {ScenarioFlowEdge, ScenarioFlowNode} from '@/modules/scenario/types/scenario-flow-editor'

const document: ScenarioFlowDocument = {
    format: 'scenario-flow',
    version: 1,
    viewport: {x: 0, y: 0, zoom: 1},
    blocks: [createScenarioFlowNode('start', {x: 0, y: 0})],
    connections: [],
}

describe('useScenarioFlowDocument', () => {
    it('не сериализует и не публикует документ при глубоком изменении Vue Flow state', async () => {
        const onDirtyChange = vi.fn()
        const onFlowStateApplied = vi.fn()
        const nodes = ref<ScenarioFlowNode[]>([])
        const edges = ref<ScenarioFlowEdge[]>([])
        const viewport = ref({x: 0, y: 0, zoom: 1})
        const scope = effectScope()
        const flow = scope.run(() => useScenarioFlowDocument({
            modelValue: () => document,
            scenarios: () => [],
            nodes,
            edges,
            viewport,
            resetSelection: vi.fn(),
            onDirtyChange,
            onFlowStateApplied,
        }))

        expect(flow).toBeDefined()
        expect(onFlowStateApplied).toHaveBeenCalledOnce()
        onDirtyChange.mockClear()
        nodes.value[0]!.position.x = 150
        nodes.value[0]!.selected = true
        await nextTick()

        expect(onDirtyChange).not.toHaveBeenCalled()
        expect(flow!.currentFlowDocument().blocks[0]!.position.x).toBe(150)

        flow!.onNodeDragStop()
        expect(onDirtyChange).toHaveBeenCalledOnce()
        expect(onDirtyChange).toHaveBeenCalledWith(true)
        scope.stop()
    })

    it('сбрасывает dirty state только после явной фиксации сохранения', () => {
        const onDirtyChange = vi.fn()
        const scope = effectScope()
        const flow = scope.run(() => useScenarioFlowDocument({
            modelValue: () => document,
            scenarios: () => [],
            nodes: ref<ScenarioFlowNode[]>([]),
            edges: ref<ScenarioFlowEdge[]>([]),
            viewport: ref({x: 0, y: 0, zoom: 1}),
            resetSelection: vi.fn(),
            onDirtyChange,
        }))!

        onDirtyChange.mockClear()
        flow.markChanged()
        flow.markSaved()

        expect(onDirtyChange.mock.calls).toEqual([[true], [false]])
        scope.stop()
    })
})
