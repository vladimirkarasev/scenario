import {computed, ref, watch, type Ref} from 'vue'
import {initYmaps} from 'vue-yandex-maps'
import type {LngLat, RouteOptions} from '@yandex/ymaps3-types'
import {useYandexGeocode} from '@/modules/scenario/composables/useYandexGeocode'
import {DEFAULT_MAP_CENTER, fromLngLat, toLngLat} from '@/modules/scenario/lib/yandex-map-coordinates'
import type {RoutingMode} from '@/modules/scenario/lib/scenario-block-fields'
import type {
    GeocodeResult,
    LatLng,
    RouteAlternative,
    RouteLeg,
    RouteValue,
    RouteWaypointValue,
} from '@/modules/scenario/types/yandex-map'

interface SurveyRouteOptions {
    maxWaypoints: () => number
    modalOpen: Ref<boolean>
    modelValue: () => RouteValue | null
    routingMode: () => RoutingMode
    showAlternatives: () => boolean
}

export function useSurveyRoute(options: SurveyRouteOptions) {
    const routerApiKey = import.meta.env.VITE_YANDEX_MAPS_ROUTER_API_KEY?.trim()
    const waypoints = ref<RouteWaypointValue[]>([])
    const alternatives = ref<RouteAlternative[]>([])
    const selectedIndex = ref(0)
    const building = ref(false)
    const routeError = ref<string | null>(null)
    const {reverseGeocode} = useYandexGeocode()
    let buildSequence = 0

    const routeType = computed<RouteOptions['type']>(() => {
        if (options.routingMode() === 'pedestrian') return 'walking'
        if (options.routingMode() === 'masstransit') return 'transit'
        return 'driving'
    })
    const mapCenter = computed<LatLng>(() => {
        const first = waypoints.value[0]
        return first ? [first.lat, first.lng] : DEFAULT_MAP_CENTER
    })
    const selectedRoute = computed(() => alternatives.value[selectedIndex.value]?.route ?? null)
    const mapBounds = computed(() => selectedRoute.value?.properties.bounds ?? null)
    const totalDistanceMeters = computed(() => alternatives.value[selectedIndex.value]?.distanceMeters ?? 0)
    const totalDurationSeconds = computed(() => alternatives.value[selectedIndex.value]?.durationSeconds ?? 0)
    const currentLegs = computed(() => alternatives.value[selectedIndex.value]?.legs ?? [])
    const canAddWaypoint = computed(() => waypoints.value.length < options.maxWaypoints())

    function resetFromModel(): void {
        const value = options.modelValue()
        waypoints.value = value?.waypoints.map(waypoint => ({...waypoint})) ?? []
        selectedIndex.value = value?.selectedRouteIndex ?? 0
    }

    async function buildLegs(points: LngLat[], type: RouteOptions['type']): Promise<RouteLeg[]> {
        const responses = await Promise.all(points.slice(0, -1).map((point, index) =>
            window.ymaps3.route({points: [point, points[index + 1]], type})))

        return responses.map((variants, index) => {
            const route = variants[0]?.toRoute()

            return {
                from: waypoints.value[index]?.address ?? `Точка ${index + 1}`,
                to: waypoints.value[index + 1]?.address ?? `Точка ${index + 2}`,
                distanceMeters: route?.properties.length ?? 0,
                durationSeconds: route?.properties.duration ?? 0,
            }
        })
    }

    async function rebuildRoute(): Promise<void> {
        const sequence = ++buildSequence

        if (!options.modalOpen.value || waypoints.value.length < 2) {
            alternatives.value = []
            routeError.value = null
            return
        }

        if (!routerApiKey) {
            alternatives.value = []
            routeError.value = 'Для построения маршрута добавьте ключ Router API в VITE_YANDEX_MAPS_ROUTER_API_KEY'
            return
        }

        building.value = true
        routeError.value = null

        try {
            await initYmaps()
            const points = waypoints.value.flatMap((waypoint) => {
                const coordinates = toLngLat(waypoint)
                return coordinates ? [coordinates] : []
            })

            if (points.length !== waypoints.value.length) {
                throw new Error('Маршрут содержит некорректные координаты')
            }

            const [responses, legs] = await Promise.all([
                window.ymaps3.route({points, type: routeType.value, bounds: true}),
                buildLegs(points, routeType.value),
            ])

            if (sequence !== buildSequence) return

            const variants = options.showAlternatives() ? responses : responses.slice(0, 1)
            alternatives.value = variants.map((response) => {
                const route = response.toRoute()

                return {
                    distanceMeters: route.properties.length ?? 0,
                    durationSeconds: route.properties.duration ?? 0,
                    legs,
                    route,
                }
            })

            if (selectedIndex.value >= alternatives.value.length) selectedIndex.value = 0
        } catch (error: unknown) {
            if (sequence !== buildSequence) return
            alternatives.value = []
            routeError.value = error instanceof Error ? error.message : 'Не удалось построить маршрут'
        } finally {
            if (sequence === buildSequence) building.value = false
        }
    }

    async function addPointByCoords(lat: number, lng: number): Promise<void> {
        if (!canAddWaypoint.value) return

        let address = `${lat.toFixed(5)}, ${lng.toFixed(5)}`

        try {
            const result = await reverseGeocode(lat, lng)
            if (result) address = result.address
        } catch (error: unknown) {
            console.error('[useSurveyRoute] reverseGeocode failed', error)
        }

        waypoints.value = [...waypoints.value, {lat, lng, address}]
    }

    function addPointBySearch(result: GeocodeResult): void {
        if (!canAddWaypoint.value) return
        waypoints.value = [...waypoints.value, result]
    }

    async function updateWaypoint(index: number, coordinates: LngLat): Promise<void> {
        const [lat, lng] = fromLngLat(coordinates)
        let address = `${lat.toFixed(5)}, ${lng.toFixed(5)}`

        try {
            const result = await reverseGeocode(lat, lng)
            if (result) address = result.address
        } catch (error: unknown) {
            console.error('[useSurveyRoute] reverseGeocode failed', error)
        }

        waypoints.value = waypoints.value.map((waypoint, waypointIndex) =>
            waypointIndex === index ? {lat, lng, address} : waypoint)
    }

    function removeWaypoint(index: number): void {
        waypoints.value = waypoints.value.filter((_, waypointIndex) => waypointIndex !== index)
    }

    function selectAlternative(index: number): void {
        selectedIndex.value = index
    }

    function createValue(): RouteValue | null {
        if (waypoints.value.length < 2) return null

        return {
            waypoints: waypoints.value,
            selectedRouteIndex: selectedIndex.value,
            legs: currentLegs.value,
            totalDistanceMeters: totalDistanceMeters.value,
            totalDurationSeconds: totalDurationSeconds.value,
        }
    }

    watch(
        [waypoints, options.modalOpen, routeType],
        () => void rebuildRoute(),
    )
    watch(options.modalOpen, (open) => {
        if (open) resetFromModel()
    })
    watch(options.modelValue, () => {
        if (!options.modalOpen.value) resetFromModel()
    }, {immediate: true})

    return {
        addPointByCoords,
        addPointBySearch,
        alternatives,
        building,
        canAddWaypoint,
        createValue,
        currentLegs,
        mapBounds,
        mapCenter,
        removeWaypoint,
        routeError,
        selectedIndex,
        selectedRoute,
        selectAlternative,
        totalDistanceMeters,
        totalDurationSeconds,
        updateWaypoint,
        waypoints,
    }
}
