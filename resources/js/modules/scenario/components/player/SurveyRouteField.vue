<script setup lang="ts">
import {ref} from 'vue'
import {Button} from '@/components/ui/button'
import {Loader2, MapPin, Route as RouteIcon, Trash2, X} from 'lucide-vue-next'
import {YandexMapDefaultMarker, YandexMapFeature} from 'vue-yandex-maps'
import type {DrawingStyle, LngLat} from '@yandex/ymaps3-types'
import MapModalDialog from '@/modules/scenario/components/block-editor/map/MapModalDialog.vue'
import MapAddressSearch from '@/modules/scenario/components/map/MapAddressSearch.vue'
import ScenarioYandexMap from '@/modules/scenario/components/map/ScenarioYandexMap.vue'
import {useSurveyRoute} from '@/modules/scenario/composables/useSurveyRoute'
import {toLngLat} from '@/modules/scenario/lib/yandex-map-coordinates'
import type {RoutingMode} from '@/modules/scenario/lib/scenario-block-fields'
import type {GeocodeResult, RouteValue} from '@/modules/scenario/types/yandex-map'

const props = withDefaults(defineProps<{
  modelValue: RouteValue | null
  routingMode?: RoutingMode
  showAlternatives?: boolean
  maxWaypoints?: number
  disabled?: boolean
  error?: boolean
}>(), {
  routingMode: 'auto',
  showAlternatives: true,
  maxWaypoints: 10,
  disabled: false,
  error: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: RouteValue | null]
}>()

const ROUTE_STYLE: DrawingStyle = {
  fillRule: 'nonzero',
  fill: '#2563eb',
  fillOpacity: 0.9,
  stroke: [
    {width: 10, color: '#ffffff'},
    {width: 6, color: '#2563eb'},
  ],
}

const modalOpen = ref(false)
const {
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
} = useSurveyRoute({
  maxWaypoints: () => props.maxWaypoints,
  modalOpen,
  modelValue: () => props.modelValue,
  routingMode: () => props.routingMode,
  showAlternatives: () => props.showAlternatives,
})

function formatDistance(meters: number): string {
  return meters >= 1000 ? `${(meters / 1000).toFixed(1)} км` : `${Math.round(meters)} м`
}

function formatDuration(seconds: number): string {
  const minutes = Math.round(seconds / 60)
  if (minutes < 60) return `${minutes} мин`
  return `${Math.floor(minutes / 60)} ч ${minutes % 60} мин`
}

function handleMapClick(lat: number, lng: number): void {
  if (!props.disabled) void addPointByCoords(lat, lng)
}

function handleSearchResult(result: GeocodeResult): void {
  addPointBySearch(result)
}

function handleWaypointDragEnd(index: number, coordinates: LngLat): void {
  void updateWaypoint(index, coordinates)
}

function clearRoute(): void {
  emit('update:modelValue', null)
}

function save(): void {
  emit('update:modelValue', createValue())
  modalOpen.value = false
}
</script>

<template>
  <div class="space-y-1.5">
    <div class="flex items-center gap-1.5">
      <Button
          type="button"
          variant="outline"
          class="h-9 flex-1 justify-start rounded-xl"
          :class="error ? 'border-destructive' : ''"
          :disabled="disabled"
          @click="modalOpen = true"
      >
        <RouteIcon class="mr-2 size-4 shrink-0" />
        <span v-if="modelValue" class="truncate">
          {{ modelValue.waypoints.length }} точ., {{ formatDistance(modelValue.totalDistanceMeters) }}
        </span>
        <span v-else>Построить маршрут</span>
      </Button>
      <Button
          v-if="modelValue && !disabled"
          type="button"
          variant="outline"
          size="icon"
          class="size-9 shrink-0 rounded-xl text-muted-foreground hover:text-foreground"
          title="Удалить маршрут"
          @click="clearRoute"
      >
        <X class="size-4" />
      </Button>
    </div>

    <MapModalDialog v-model:open="modalOpen" title="Маршрут">
      <div class="flex min-h-0 flex-1">
        <div class="flex w-72 shrink-0 flex-col border-r border-slate-100">
          <MapAddressSearch
              placeholder="Добавить точку по адресу..."
              hint="Или кликните по карте, чтобы добавить точку"
              :disabled="disabled || !canAddWaypoint"
              @select="handleSearchResult"
          />

          <div class="min-h-0 flex-1 overflow-y-auto">
            <div class="p-3">
              <div class="mb-2 flex items-center justify-between gap-2">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Точки маршрута</p>
                <span class="text-[11px] tabular-nums text-slate-400">{{ waypoints.length }}/{{ maxWaypoints }}</span>
              </div>

              <div v-if="waypoints.length" class="space-y-1.5">
                <div
                    v-for="(waypoint, index) in waypoints"
                    :key="`${index}-${waypoint.lat}-${waypoint.lng}`"
                    class="rounded-xl border border-slate-100 bg-slate-50 p-2.5"
                >
                  <div class="flex items-start gap-2.5">
                    <span class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-white text-[11px] font-semibold text-blue-600 shadow-sm">
                      {{ index + 1 }}
                    </span>
                    <div class="min-w-0 flex-1">
                      <p class="text-xs font-medium leading-5 text-slate-700">{{ waypoint.address }}</p>
                      <p class="font-mono text-[10px] text-slate-400">
                        {{ waypoint.lat.toFixed(5) }}, {{ waypoint.lng.toFixed(5) }}
                      </p>
                    </div>
                    <Button
                        v-if="!disabled"
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-7 shrink-0 text-slate-400 hover:bg-destructive/10 hover:text-destructive"
                        title="Удалить точку"
                        @click="removeWaypoint(index)"
                    >
                      <Trash2 class="size-3.5" />
                    </Button>
                  </div>
                </div>
              </div>

              <p v-else class="rounded-xl border border-dashed border-slate-200 px-3 py-2.5 text-xs text-slate-400">
                Добавьте минимум две точки через поиск или карту
              </p>
            </div>

            <div v-if="showAlternatives && alternatives.length > 1" class="border-t border-slate-100 p-3">
              <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Варианты маршрута</p>
              <div class="space-y-1.5">
                <Button
                    v-for="(alternative, index) in alternatives"
                    :key="index"
                    type="button"
                    variant="ghost"
                    class="h-auto w-full justify-between rounded-xl border px-3 py-2 text-left text-xs"
                    :class="selectedIndex === index
                      ? 'border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-50'
                      : 'border-slate-100 bg-slate-50 text-slate-600 hover:bg-slate-100'"
                    @click="selectAlternative(index)"
                >
                  <span>Маршрут {{ index + 1 }}</span>
                  <span class="ml-2 shrink-0 tabular-nums">
                    {{ formatDistance(alternative.distanceMeters) }} · {{ formatDuration(alternative.durationSeconds) }}
                  </span>
                </Button>
              </div>
            </div>

            <div v-if="currentLegs.length" class="border-t border-slate-100 p-3">
              <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Путь</p>
              <div class="rounded-xl border border-slate-100 bg-slate-50 p-3">
                <div
                    v-for="(leg, index) in currentLegs"
                    :key="index"
                    class="flex gap-2 py-1 text-[11px] text-slate-600"
                >
                  <MapPin class="mt-0.5 size-3 shrink-0 text-slate-400" />
                  <span class="min-w-0 flex-1 truncate">{{ leg.from }} → {{ leg.to }}</span>
                  <span class="shrink-0 tabular-nums">{{ formatDistance(leg.distanceMeters) }}</span>
                </div>
                <div class="mt-2 flex justify-between border-t border-slate-200 pt-2 text-xs font-semibold text-slate-700">
                  <span>Итого</span>
                  <span class="tabular-nums">
                    {{ formatDistance(totalDistanceMeters) }} · {{ formatDuration(totalDurationSeconds) }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="relative flex-1">
          <ScenarioYandexMap
              :center="mapCenter"
              :bounds="mapBounds"
              :zoom="12"
              :interactive="!disabled"
              @click="handleMapClick"
          >
            <YandexMapFeature
                v-if="selectedRoute"
                :settings="{...selectedRoute, style: ROUTE_STYLE}"
            />
            <YandexMapDefaultMarker
                v-for="(waypoint, index) in waypoints"
                :key="`${index}-${waypoint.lat}-${waypoint.lng}`"
                :settings="{
                  coordinates: toLngLat(waypoint)!,
                  title: String(index + 1),
                  subtitle: waypoint.address,
                  draggable: !disabled,
                  onDragEnd: (coordinates) => handleWaypointDragEnd(index, coordinates),
                }"
            />
          </ScenarioYandexMap>

          <div
              v-if="routeError"
              class="absolute inset-x-4 top-4 z-10 rounded-xl border border-destructive/20 bg-destructive px-3 py-2 text-sm text-destructive-foreground shadow"
          >
            {{ routeError }}
          </div>
          <div v-if="building" class="absolute inset-0 flex items-center justify-center bg-white/60">
            <Loader2 class="size-6 animate-spin text-slate-400" />
          </div>
        </div>
      </div>

      <template #footer>
        <Button type="button" variant="outline" @click="modalOpen = false">Отмена</Button>
        <Button type="button" :disabled="waypoints.length < 2 || building" @click="save">
          Сохранить маршрут
        </Button>
      </template>
    </MapModalDialog>
  </div>
</template>
