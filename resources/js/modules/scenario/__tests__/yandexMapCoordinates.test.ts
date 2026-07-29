import {describe, expect, it} from 'vitest'
import {
    DEFAULT_MAP_CENTER,
    fromLngLat,
    isValidMapPoint,
    normalizeMapCenter,
    normalizeMapZoom,
    toLngLat,
} from '@/modules/scenario/lib/yandex-map-coordinates'

describe('yandex map coordinates', () => {
    it('converts coordinates between application and Yandex Maps formats', () => {
        expect(toLngLat({lat: 55.751244, lng: 37.618423})).toEqual([37.618423, 55.751244])
        expect(fromLngLat([37.618423, 55.751244])).toEqual([55.751244, 37.618423])
    })

    it('rejects coordinates outside geographic bounds', () => {
        expect(isValidMapPoint(90, 180)).toBe(true)
        expect(isValidMapPoint(91, 37)).toBe(false)
        expect(isValidMapPoint(55, -181)).toBe(false)
        expect(toLngLat({lat: Number.NaN, lng: 37})).toBeNull()
    })

    it('normalizes center and zoom values', () => {
        expect(normalizeMapCenter([55, 37])).toEqual([55, 37])
        expect(normalizeMapCenter([100, 37])).toEqual(DEFAULT_MAP_CENTER)
        expect(normalizeMapZoom(-5)).toBe(0)
        expect(normalizeMapZoom(25)).toBe(19)
        expect(normalizeMapZoom('12', 10)).toBe(10)
    })
})
