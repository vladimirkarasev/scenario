import {describe, expect, it} from 'vitest'
import {
    FLOW_MINIMAP_NODE_LIMIT,
    FLOW_VIRTUALIZATION_THRESHOLD,
    scenarioFlowRenderPolicy,
} from '@/modules/scenario/lib/scenario-flow-performance'

describe('scenario flow render policy', () => {
    it('не включает виртуализацию для небольшого графа', () => {
        expect(scenarioFlowRenderPolicy(FLOW_VIRTUALIZATION_THRESHOLD - 1)).toEqual({
            onlyRenderVisibleElements: false,
            renderMiniMap: true,
        })
    })

    it('включает виртуализацию после достижения порога', () => {
        expect(scenarioFlowRenderPolicy(FLOW_VIRTUALIZATION_THRESHOLD).onlyRenderVisibleElements).toBe(true)
    })

    it('не рендерит MiniMap для большого графа', () => {
        expect(scenarioFlowRenderPolicy(FLOW_MINIMAP_NODE_LIMIT + 1).renderMiniMap).toBe(false)
    })
})
