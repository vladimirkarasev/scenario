import {computed, nextTick, ref} from 'vue'
import {describe, expect, it, vi} from 'vitest'
import {useScenarioFlowInspector} from '@/modules/scenario/composables/useScenarioFlowInspector'
import type {ScenarioFlowNode} from '@/modules/scenario/types/scenario-flow-editor'

function conditionNode(): ScenarioFlowNode {
    return {
        id: 'condition-1',
        type: 'condition',
        position: {x: 0, y: 0},
        data: {
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
    }
}

describe('useScenarioFlowInspector', () => {
    it('хранит правки локально и обновляет node только по сохранению', async () => {
        const nodes = ref<ScenarioFlowNode[]>([conditionNode()])
        const selectedId = ref<string | null>(null)
        const selectedNode = computed(() => nodes.value.find((node) => node.id === selectedId.value) ?? null)
        const drawerOpen = ref(true)
        const onChanged = vi.fn()
        const inspector = useScenarioFlowInspector({
            nodes,
            selectedNode,
            drawerOpen,
            scenarios: () => [],
            onChanged,
        })

        selectedId.value = 'condition-1'
        await nextTick()
        inspector.inspectorDraft.title = 'Новое условие'

        expect(nodes.value[0]?.data.title).toBe('Условие')
        expect(onChanged).not.toHaveBeenCalled()

        inspector.syncSelectedNode()
        inspector.commitInspector()

        expect(nodes.value[0]?.data.title).toBe('Новое условие')
        expect(onChanged).toHaveBeenCalledOnce()
        expect(drawerOpen.value).toBe(false)

        inspector.inspectorDraft.title = 'Несохранённое условие'
        inspector.cancelInspector()

        expect(nodes.value[0]?.data.title).toBe('Новое условие')
    })
})
