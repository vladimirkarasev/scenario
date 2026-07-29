<script lang="ts">
import {markRaw} from 'vue'
import {MapPin} from 'lucide-vue-next'

export const fieldMeta = {type: 'map_point', label: 'Карта', icon: markRaw(MapPin)}
</script>

<script setup lang="ts">
import {computed, ref} from 'vue'
import {Button} from '@/components/ui/button'
import {Input} from '@/components/ui/input'
import {Label} from '@/components/ui/label'
import {Loader2, Trash2, X} from 'lucide-vue-next'
import {YandexMapDefaultMarker} from 'vue-yandex-maps'
import type {LngLat} from '@yandex/ymaps3-types'
import MapModalDialog from '@/modules/scenario/components/block-editor/map/MapModalDialog.vue'
import MapAddressSearch from '@/modules/scenario/components/map/MapAddressSearch.vue'
import ScenarioYandexMap from '@/modules/scenario/components/map/ScenarioYandexMap.vue'
import {useYandexGeocode} from '@/modules/scenario/composables/useYandexGeocode'
import {DEFAULT_MAP_CENTER, fromLngLat, toLngLat} from '@/modules/scenario/lib/yandex-map-coordinates'
import type {GeocodeResult, LatLng} from '@/modules/scenario/types/yandex-map'
import type {MapPointBlockField} from '../../../lib/scenario-block-fields'

const props = defineProps<{ field: MapPointBlockField; disabled?: boolean }>()
const emit = defineEmits<{ update: [patch: Partial<MapPointBlockField>] }>()
defineOptions({inheritAttrs: false})

const modalOpen = ref(false)
const geocoding = ref(false)
const {reverseGeocode} = useYandexGeocode()

const mapCenter = ref<LatLng>(
    props.field.lat !== null && props.field.lng !== null
        ? [props.field.lat, props.field.lng]
        : DEFAULT_MAP_CENTER,
)
const markerCoordinates = computed(() => toLngLat(
    props.field.lat !== null && props.field.lng !== null
        ? {lat: props.field.lat, lng: props.field.lng}
        : null,
))

async function setPoint(lat: number, lng: number): Promise<void> {
  emit('update', {lat, lng})

  geocoding.value = true
  try {
    const result = await reverseGeocode(lat, lng)
    emit('update', {address: result?.address ?? ''})
  } catch (e: unknown) {
    console.error('[MapPointFieldSettings] reverseGeocode failed', e)
  } finally {
    geocoding.value = false
  }
}

function selectSearchResult(result: GeocodeResult): void {
  mapCenter.value = [result.lat, result.lng]
  emit('update', {lat: result.lat, lng: result.lng, address: result.address})
}

function handleMarkerDragEnd(coordinates: LngLat): void {
  const [lat, lng] = fromLngLat(coordinates)
  void setPoint(lat, lng)
}

function clearPoint(): void {
  emit('update', {lat: null, lng: null, address: ''})
}
</script>

<template>
  <div class="space-y-4">
    <div class="space-y-1.5">
      <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Точка на карте</Label>
      <div class="flex items-center gap-1.5">
        <Button type="button" variant="outline" class="h-9 flex-1 justify-start" :disabled="disabled" @click="modalOpen = true">
          <MapPin class="mr-2 size-4 shrink-0" />
          <span class="truncate">{{ field.address || 'Открыть карту' }}</span>
        </Button>
        <button
            v-if="field.address && !disabled"
            type="button"
            class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-400 transition hover:border-slate-300 hover:text-slate-600"
            title="Удалить точку"
            @click="clearPoint"
        >
          <X class="size-4" />
        </button>
      </div>
    </div>

    <div class="space-y-1.5">
      <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Адрес</Label>
      <Input
          :model-value="field.address"
          :disabled="disabled"
          class="h-9 text-sm"
          placeholder="Определяется по точке на карте, можно поправить вручную"
          @update:model-value="emit('update', { address: String($event) })"
      />
    </div>

    <div class="space-y-1.5">
      <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Масштаб по умолчанию</Label>
      <Input
          :model-value="field.defaultZoom"
          type="number"
          min="1"
          max="19"
          class="h-9 text-sm"
          :disabled="disabled"
          @update:model-value="emit('update', { defaultZoom: Number($event) || 15 })"
      />
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

            <div v-else-if="field.address" class="rounded-xl border border-slate-100 bg-slate-50 p-3">
              <div class="flex items-start gap-2.5">
                <span class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-white shadow-sm">
                  <MapPin class="size-3.5 text-blue-500" />
                </span>
                <div class="min-w-0 space-y-0.5">
                  <p class="text-xs font-medium text-slate-700">{{ field.address }}</p>
                  <p class="font-mono text-[11px] text-slate-400">{{ field.lat?.toFixed(5) }}, {{ field.lng?.toFixed(5) }}</p>
                </div>
              </div>
              <button
                  type="button"
                  class="mt-2.5 flex items-center gap-1.5 rounded-lg px-1.5 py-1 text-xs text-destructive transition hover:bg-destructive/10"
                  @click="clearPoint"
              >
                <Trash2 class="size-3.5" />
                Удалить точку
              </button>
            </div>

            <p v-else class="rounded-xl border border-dashed border-slate-200 px-3 py-2.5 text-xs text-slate-400">
              Кликните по карте или найдите адрес выше
            </p>
          </div>
        </div>

        <div class="relative flex-1">
          <ScenarioYandexMap
              :center="mapCenter"
              :zoom="field.defaultZoom"
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
        </div>
      </div>

      <template #footer>
        <Button type="button" @click="modalOpen = false">Готово</Button>
      </template>
    </MapModalDialog>
  </div>
</template>
