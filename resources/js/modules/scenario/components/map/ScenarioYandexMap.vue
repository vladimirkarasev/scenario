<script setup lang="ts">
import {computed, shallowRef} from 'vue'
import {
  YandexMap,
  YandexMapControls,
  YandexMapDefaultFeaturesLayer,
  YandexMapDefaultSchemeLayer,
  YandexMapListener,
  YandexMapZoomControl,
  yandexMapLoadError,
  yandexMapLoadStatus,
} from 'vue-yandex-maps'
import type {DomEvent, LngLat, LngLatBounds, YMap} from '@yandex/ymaps3-types'
import {Loader2} from 'lucide-vue-next'
import {
  fromLngLat,
  normalizeMapCenter,
  normalizeMapZoom,
} from '@/modules/scenario/lib/yandex-map-coordinates'
import type {LatLng} from '@/modules/scenario/types/yandex-map'

const props = withDefaults(defineProps<{
  center?: LatLng
  bounds?: LngLatBounds | null
  zoom?: number
  interactive?: boolean
}>(), {
  center: () => [55.751244, 37.618423],
  bounds: null,
  zoom: 12,
  interactive: true,
})

const emit = defineEmits<{
  click: [lat: number, lng: number]
}>()

const map = shallowRef<YMap | null>(null)

const location = computed(() => {
  if (props.bounds) {
    return {
      bounds: props.bounds,
      duration: 250,
    }
  }

  const [lat, lng] = normalizeMapCenter(props.center)

  return {
    center: [lng, lat] as LngLat,
    zoom: normalizeMapZoom(props.zoom),
    duration: 250,
  }
})

const behaviors = computed(() => props.interactive
    ? ['drag', 'pinchZoom', 'dblClick'] as const
    : [] as const)

const loading = computed(() => ['pending', 'loading'].includes(yandexMapLoadStatus.value))
const mapErrorMessage = computed(() => {
  const error = yandexMapLoadError.value

  if (!error) return null
  if (error instanceof Error) return error.message

  return typeof error === 'string' ? error : 'Не удалось загрузить Яндекс Карты'
})

function handleClick(_: unknown, event: DomEvent): void {
  const [lat, lng] = fromLngLat(event.coordinates)
  emit('click', lat, lng)
}
</script>

<template>
  <div class="relative size-full">
    <YandexMap
        v-model="map"
        width="100%"
        height="100%"
        :settings="{
          location,
          behaviors: [...behaviors],
          showScaleInCopyrights: true,
        }"
    >
      <YandexMapDefaultSchemeLayer />
      <YandexMapDefaultFeaturesLayer />
      <YandexMapListener :settings="{ onClick: handleClick }" />
      <YandexMapControls :settings="{ position: 'right' }">
        <YandexMapZoomControl />
      </YandexMapControls>
      <slot :map="map" />
    </YandexMap>

    <div v-if="mapErrorMessage" class="absolute inset-0 z-10 flex items-center justify-center bg-white p-6 text-center text-sm text-destructive">
      {{ mapErrorMessage }}
    </div>
    <div v-else-if="loading" class="absolute inset-0 z-10 flex items-center justify-center bg-white/60">
      <Loader2 class="size-6 animate-spin text-slate-400" />
    </div>
  </div>
</template>
