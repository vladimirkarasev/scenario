export type LatLng = [lat: number, lng: number]

export interface YandexMapPoint {
    lat: number
    lng: number
}

export interface GeocodeResult extends YandexMapPoint {
    address: string
}

export interface MapPointValue extends YandexMapPoint {
    address: string
}

export interface RouteWaypointValue extends YandexMapPoint {
    address: string
}

export interface RouteLeg {
    from: string
    to: string
    distanceMeters: number
    durationSeconds: number
}

export interface RouteValue {
    waypoints: RouteWaypointValue[]
    selectedRouteIndex: number
    legs: RouteLeg[]
    totalDistanceMeters: number
    totalDurationSeconds: number
}

export interface RouteAlternative {
    distanceMeters: number
    durationSeconds: number
    legs: RouteLeg[]
    route: RouteFeature
}
import type {RouteFeature} from '@yandex/ymaps3-types'
