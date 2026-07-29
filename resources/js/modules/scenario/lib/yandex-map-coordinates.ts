import type {LngLat} from '@yandex/ymaps3-types'
import type {LatLng, YandexMapPoint} from '@/modules/scenario/types/yandex-map'

export const DEFAULT_MAP_CENTER: LatLng = [55.751244, 37.618423]

export function isValidMapPoint(lat: unknown, lng: unknown): boolean {
    return typeof lat === 'number'
        && Number.isFinite(lat)
        && lat >= -90
        && lat <= 90
        && typeof lng === 'number'
        && Number.isFinite(lng)
        && lng >= -180
        && lng <= 180
}

export function toLngLat(point: YandexMapPoint | null | undefined): LngLat | null {
    return point && isValidMapPoint(point.lat, point.lng) ? [point.lng, point.lat] : null
}

export function fromLngLat(coordinates: LngLat): LatLng {
    return [coordinates[1], coordinates[0]]
}

export function normalizeMapCenter(center: LatLng | null | undefined): LatLng {
    return center && isValidMapPoint(center[0], center[1]) ? center : DEFAULT_MAP_CENTER
}

export function normalizeMapZoom(zoom: unknown, fallback = 12): number {
    return typeof zoom === 'number' && Number.isFinite(zoom)
        ? Math.min(19, Math.max(0, zoom))
        : fallback
}
