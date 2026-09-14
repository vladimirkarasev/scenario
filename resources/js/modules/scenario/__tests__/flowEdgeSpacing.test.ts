import {describe, expect, it} from 'vitest'
import {Position} from '@vue-flow/core'
import {
    distributedEdgeOffset,
    offsetEdgeTarget,
} from '@/modules/scenario/lib/flow-edge-spacing'

describe('flow edge spacing', () => {
    it('равномерно разносит несколько стрелок относительно центра', () => {
        expect([0, 1, 2].map((index) => distributedEdgeOffset(index, 3))).toEqual([-16, 0, 16])
        expect([0, 1].map((index) => distributedEdgeOffset(index, 2))).toEqual([-8, 8])
    })

    it('ограничивает общую ширину группы стрелок', () => {
        expect(distributedEdgeOffset(0, 10)).toBe(-48)
        expect(distributedEdgeOffset(9, 10)).toBe(48)
    })

    it('сдвигает конец стрелки вдоль выбранной грани', () => {
        expect(offsetEdgeTarget(100, 200, Position.Top, 10)).toEqual({x: 110, y: 200})
        expect(offsetEdgeTarget(100, 200, Position.Left, 10)).toEqual({x: 100, y: 210})
    })
})
