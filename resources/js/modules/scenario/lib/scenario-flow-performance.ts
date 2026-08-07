export const FLOW_VIRTUALIZATION_THRESHOLD = 24
export const FLOW_MINIMAP_NODE_LIMIT = 80

export interface ScenarioFlowRenderPolicy {
    onlyRenderVisibleElements: boolean
    renderMiniMap: boolean
}

export function scenarioFlowRenderPolicy(nodeCount: number): ScenarioFlowRenderPolicy {
    return {
        onlyRenderVisibleElements: nodeCount >= FLOW_VIRTUALIZATION_THRESHOLD,
        renderMiniMap: nodeCount <= FLOW_MINIMAP_NODE_LIMIT,
    }
}
