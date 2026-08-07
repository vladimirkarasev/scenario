import {initYmaps} from 'vue-yandex-maps'
import {fromLngLat, isValidMapPoint} from '@/modules/scenario/lib/yandex-map-coordinates'
import type {GeocodeResult} from '@/modules/scenario/types/yandex-map'

function toGeocodeResult(feature: Awaited<ReturnType<typeof window.ymaps3.search>>[number]): GeocodeResult | null {
    const coordinates = feature.geometry?.coordinates
    if (!coordinates) return null

    const [lat, lng] = fromLngLat(coordinates)
    if (!isValidMapPoint(lat, lng)) return null

    const address = [feature.properties.description, feature.properties.name]
        .filter(Boolean)
        .join(', ')

    return {
        lat,
        lng,
        address,
    }
}

/**
 * Выполняет прямой и обратный поиск адресов через Yandex Maps API 3.0.
 */
export function useYandexGeocode() {
    async function reverseGeocode(lat: number, lng: number): Promise<GeocodeResult | null> {
        await initYmaps()
        const result = await window.ymaps3.search({
            text: `${lat}, ${lng}`,
            type: ['toponyms'],
            limit: 1,
        })

        return result[0] ? toGeocodeResult(result[0]) : null
    }

    async function forwardGeocode(query: string, results = 5): Promise<GeocodeResult[]> {
        if (!query.trim()) return []

        await initYmaps()
        const result = await window.ymaps3.search({
            text: query.trim(),
            type: ['toponyms'],
            limit: results,
        })

        return result.flatMap((feature) => {
            const parsed = toGeocodeResult(feature)
            return parsed ? [parsed] : []
        })
    }

    return {reverseGeocode, forwardGeocode}
}
