<script setup lang="ts">
import {Button} from '@/components/ui/button'
import {Loader2, Map as MapIcon, MapPin} from 'lucide-vue-next'
import {YandexMapClusterer, YandexMapMarker} from 'vue-yandex-maps'
import MapModalDialog from '@/modules/scenario/components/block-editor/map/MapModalDialog.vue'
import ScenarioYandexMap from '@/modules/scenario/components/map/ScenarioYandexMap.vue'
import TiptapTextRenderer from '@/modules/scenario/components/tiptap/TiptapTextRenderer.vue'
import {useSurveyDirectoryMap} from '@/modules/scenario/composables/useSurveyDirectoryMap'
import {toLngLat} from '@/modules/scenario/lib/yandex-map-coordinates'

const props = withDefaults(defineProps<{
  directoryId: string
  versionId?: string
  latKey: string
  lngKey: string
  detailDocument: unknown
  defaultZoom?: number
  disabled?: boolean
}>(), {
  versionId: '',
  defaultZoom: 12,
  disabled: false,
})

const {
  itemLabel,
  loading,
  mapCenter,
  markerItems,
  modalOpen,
  resolvedDetailDocument,
  selectedItem,
  selectItem,
  setOpen,
  tooManyRows,
} = useSurveyDirectoryMap({
  detailDocument: () => props.detailDocument,
  directoryId: props.directoryId,
  latKey: () => props.latKey,
  lngKey: () => props.lngKey,
  versionId: () => props.versionId,
})
</script>

<template>
  <div class="space-y-1.5">
    <Button
        type="button"
        variant="outline"
        class="h-9 w-full justify-start rounded-xl"
        :disabled="disabled"
        @click="setOpen(true)"
    >
      <MapIcon class="mr-2 size-4 shrink-0" />
      <span class="truncate">Открыть карту справочника</span>
    </Button>

    <MapModalDialog :open="modalOpen" title="Карта справочника" @update:open="setOpen">
      <div class="flex min-h-0 flex-1">
        <div class="flex w-72 shrink-0 flex-col border-r border-slate-100">
          <div class="border-b border-slate-100 p-3">
            <div class="flex items-center justify-between gap-2">
              <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Объекты на карте</p>
              <span v-if="!loading" class="text-[11px] tabular-nums text-slate-400">{{ markerItems.length }}</span>
            </div>
            <p class="mt-1.5 text-[11px] text-slate-400">Выберите объект в списке или на карте</p>
          </div>

          <div v-if="loading" class="flex flex-1 items-center justify-center gap-2 py-6 text-sm text-muted-foreground">
            <Loader2 class="size-4 animate-spin" />
            Загрузка...
          </div>

          <div v-else class="min-h-0 flex-1 overflow-y-auto p-3">
            <p v-if="tooManyRows" class="mb-2 rounded-xl border border-amber-100 bg-amber-50 px-3 py-2 text-[11px] text-amber-700">
              На карте {{ markerItems.length }} объектов. Для удобной работы сузьте справочник фильтром.
            </p>

            <div v-if="markerItems.length" class="space-y-1.5">
              <Button
                  v-for="item in markerItems"
                  :key="item.id"
                  type="button"
                  variant="ghost"
                  class="h-auto w-full justify-start rounded-xl border p-2.5 text-left"
                  :class="selectedItem?.id === item.id
                    ? 'border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-50'
                    : 'border-slate-100 bg-slate-50 text-slate-700 hover:bg-slate-100'"
                  @click="selectItem(item)"
              >
                <span
                    class="mr-2.5 flex size-7 shrink-0 items-center justify-center rounded-lg bg-white shadow-sm"
                    :class="selectedItem?.id === item.id ? 'text-blue-600' : 'text-slate-400'"
                >
                  <MapPin class="size-3.5" />
                </span>
                <span class="min-w-0">
                  <span class="block truncate text-xs font-medium">{{ itemLabel(item) }}</span>
                  <span class="block font-mono text-[10px] font-normal text-slate-400">
                    {{ item.lat.toFixed(5) }}, {{ item.lng.toFixed(5) }}
                  </span>
                </span>
              </Button>
            </div>

            <p v-else class="rounded-xl border border-dashed border-slate-200 px-3 py-2.5 text-xs text-slate-400">
              В справочнике нет записей с корректными координатами
            </p>
          </div>

          <div v-if="selectedItem" class="max-h-64 overflow-y-auto border-t border-slate-100 p-3">
            <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Выбранный объект</p>
            <div class="rounded-xl border border-slate-100 bg-slate-50 p-3 text-xs text-slate-700">
              <TiptapTextRenderer :document="resolvedDetailDocument" />
            </div>
          </div>
        </div>

        <div class="relative flex-1">
          <ScenarioYandexMap :center="mapCenter" :zoom="defaultZoom">
            <YandexMapClusterer :grid-size="64" zoom-on-cluster-click>
              <YandexMapMarker
                  v-for="item in markerItems"
                  :key="item.id"
                  position="top-center"
                  :settings="{
                    coordinates: toLngLat(item)!,
                    onClick: () => selectItem(item),
                  }"
              >
                <Button
                    type="button"
                    size="icon"
                    class="size-8 rounded-full border-2 border-white shadow-md"
                    :class="selectedItem?.id === item.id
                      ? 'scale-110 bg-blue-700 ring-4 ring-blue-200'
                      : 'bg-blue-600'"
                    :aria-label="itemLabel(item)"
                >
                  <MapPin class="size-4" />
                </Button>
              </YandexMapMarker>

              <template #cluster="{ length }">
                <div class="flex size-10 items-center justify-center rounded-full border-2 border-white bg-blue-600 text-xs font-semibold text-white shadow-lg">
                  {{ length }}
                </div>
              </template>
            </YandexMapClusterer>
          </ScenarioYandexMap>
        </div>
      </div>

      <template #footer>
        <Button type="button" @click="setOpen(false)">Закрыть</Button>
      </template>
    </MapModalDialog>
  </div>
</template>
