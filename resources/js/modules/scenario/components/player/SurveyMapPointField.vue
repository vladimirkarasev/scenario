<script setup lang="ts">
import {computed, ref} from 'vue'
import {Button} from '@/components/ui/button'
import {Loader2, MapPin, Trash2, X} from 'lucide-vue-next'
import {YandexMapDefaultMarker} from 'vue-yandex-maps'
import type {LngLat} from '@yandex/ymaps3-types'
import MapModalDialog from '@/modules/scenario/components/block-editor/map/MapModalDialog.vue'
import MapAddressSearch from '@/modules/scenario/components/map/MapAddressSearch.vue'
import ScenarioYandexMap from '@/modules/scenario/components/map/ScenarioYandexMap.vue'
import {useYandexGeocode} from '@/modules/scenario/composables/useYandexGeocode'
import {DEFAULT_MAP_CENTER, fromLngLat, toLngLat} from '@/modules/scenario/lib/yandex-map-coordinates'
import type {GeocodeResult, LatLng, MapPointValue} from '@/modules/scenario/types/yandex-map'

const props = withDefaults(defineProps<{
  modelValue: MapPointValue | null
  defaultZoom?: number
  disabled?: boolean
  error?: boolean
}>(), {
  defaultZoom: 15,
  disabled: false,
  error: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: MapPointValue | null]
}>()

const modalOpen = ref(false)
const geocoding = ref(false)

const {reverseGeocode} = useYandexGeocode()

const mapCenter = ref<LatLng>(
    props.modelValue ? [props.modelValue.lat, props.modelValue.lng] : DEFAULT_MAP_CENTER,
)
const markerCoordinates = computed(() => toLngLat(props.modelValue))

async function setPoint(lat: number, lng: number): Promise<void> {
  geocoding.value = true
  try {
    const result = await reverseGeocode(lat, lng)
    emit('update:modelValue', {lat, lng, address: result?.address ?? ''})
  } catch (e: unknown) {
    console.error('[SurveyMapPointField] reverseGeocode failed', e)
    emit('update:modelValue', {lat, lng, address: ''})
  } finally {
    geocoding.value = false
  }
}

function selectSearchResult(result: GeocodeResult): void {
  mapCenter.value = [result.lat, result.lng]
  emit('update:modelValue', {lat: result.lat, lng: result.lng, address: result.address})
}

function handleMarkerDragEnd(coordinates: LngLat): void {
  const [lat, lng] = fromLngLat(coordinates)
  void setPoint(lat, lng)
}

function clearPoint(): void {
  emit('update:modelValue', null)
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
        <MapPin class="mr-2 size-4 shrink-0" />
        <span class="truncate">{{ modelValue?.address || 'Указать точку на карте' }}</span>
      </Button>
      <Button
          v-if="modelValue && !disabled"
          type="button"
          variant="outline"
          size="icon"
          class="size-9 shrink-0 rounded-xl text-muted-foreground hover:text-foreground"
          title="Удалить точку"
          @click="clearPoint"
      >
        <X class="size-4" />
      </Button>
    </div>

    <MapModalDialog v-model:open="modalOpen" title="Точка на карте">
      <div class="flex min-h-0 flex-1">
        <div class="flex w-72 shrink-0 flex-col border-r border-slate-100">
          <MapAddressSearch :disabled="disabled" @select="selectSearchResult" />

          <div class="p-3">
            <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Выбранная точка</p>

            <div v-if="geocoding" class="flex items-center gap-2 rounded-xl border border-slate-100 bg-slate-50 px-3 py-2.5 text-xs text-slate-400">
              <Loader2 class="size-3.5 shrink-0 animate-spin" />
              Определяем адрес...
            </div>

            <div v-else-if="modelValue" class="rounded-xl border border-slate-100 bg-slate-50 p-3">
              <div class="flex items-start gap-2.5">
                <span class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-white shadow-sm">
                  <MapPin class="size-3.5 text-blue-500" />
                </span>
                <div class="min-w-0 space-y-0.5">
                  <p class="text-xs font-medium text-slate-700">{{ modelValue.address }}</p>
                  <p class="font-mono text-[11px] text-slate-400">{{ modelValue.lat.toFixed(5) }}, {{ modelValue.lng.toFixed(5) }}</p>
                </div>
              </div>
              <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  class="mt-2.5 h-7 px-1.5 text-xs text-destructive hover:bg-destructive/10 hover:text-destructive"
                  @click="clearPoint"
              >
                <Trash2 class="mr-1.5 size-3.5" />
                Удалить точку
              </Button>
            </div>

            <p v-else class="rounded-xl border border-dashed border-slate-200 px-3 py-2.5 text-xs text-slate-400">
              Кликните по карте или найдите адрес выше
            </p>
          </div>
        </div>

        <div class="relative flex-1">
          <ScenarioYandexMap
              :center="mapCenter"
              :zoom="defaultZoom"
              :interactive="!disabled"
              @click="(lat, lng) => !disabled && setPoint(lat, lng)"
          >
            <YandexMapDefaultMarker
                v-if="markerCoordinates"
                :settings="{
                  coordinates: markerCoordinates,
                  draggable: !disabled,
                  onDragEnd: handleMarkerDragEnd,
                }"
            />
          </ScenarioYandexMap>
          <div v-if="geocoding" class="absolute inset-0 flex items-center justify-center bg-white/60">
            <Loader2 class="size-6 animate-spin text-slate-400" />
          </div>
        </div>
      </div>

      <template #footer>
        <Button type="button" @click="modalOpen = false">Готово</Button>
      </template>
    </MapModalDialog>
  </div>
</template>
