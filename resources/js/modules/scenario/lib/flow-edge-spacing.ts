import {Position} from '@vue-flow/core'

const EDGE_GAP = 16
const MAX_EDGE_SPAN = 96

export function distributedEdgeOffset(index: number, count: number): number {
    if (count <= 1) {
        return 0
    }

    const gap = Math.min(EDGE_GAP, MAX_EDGE_SPAN / (count - 1))

    return (index - (count - 1) / 2) * gap
}

export function offsetEdgeTarget(
    x: number,
    y: number,
    position: Position,
    offset: number,
): {x: number; y: number} {
    if (position === Position.Left || position === Position.Right) {
        return {x, y: y + offset}
    }

    return {x: x + offset, y}
}
