<script setup lang="ts">
import {Loader2, MapPin, Search} from 'lucide-vue-next'
import {Input} from '@/components/ui/input'
import {Button} from '@/components/ui/button'
import {useMapAddressSearch} from '@/modules/scenario/composables/useMapAddressSearch'
import type {GeocodeResult} from '@/modules/scenario/types/yandex-map'

const props = withDefaults(defineProps<{
  disabled?: boolean
  hint?: string
  limit?: number
  placeholder?: string
}>(), {
  disabled: false,
  hint: 'Или кликните по карте',
  limit: 5,
  placeholder: 'Найти адрес...',
})

const emit = defineEmits<{
  select: [result: GeocodeResult]
}>()

const {clear, query, results, searching} = useMapAddressSearch(props.limit)

function selectResult(result: GeocodeResult): void {
  emit('select', result)
  clear()
}
</script>

<template>
  <div class="relative z-20 border-b border-slate-100 p-3">
    <div class="relative">
      <Loader2
          v-if="searching"
          class="pointer-events-none absolute left-2.5 top-1/2 z-10 size-3.5 -translate-y-1/2 animate-spin text-slate-400"
      />
      <Search
          v-else
          class="pointer-events-none absolute left-2.5 top-1/2 z-10 size-3.5 -translate-y-1/2 text-slate-400"
      />
      <Input
          v-model="query"
          type="text"
          :placeholder="placeholder"
          autocomplete="off"
          class="h-9 rounded-lg pl-8 pr-2.5 text-sm"
          :disabled="disabled"
      />
    </div>

    <div
        v-if="results.length"
        class="absolute left-3 right-3 top-[3.25rem] z-50 max-h-64 overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg"
    >
      <Button
          v-for="(result, index) in results"
          :key="`${result.lat}-${result.lng}-${index}`"
          type="button"
          variant="ghost"
          class="h-auto w-full justify-start rounded-none px-2.5 py-2 text-left text-xs first:rounded-t-lg last:rounded-b-lg"
          @click="selectResult(result)"
      >
        <MapPin class="mr-2 mt-0.5 size-3.5 shrink-0 self-start text-slate-400" />
        <span class="min-w-0 whitespace-normal">{{ result.address }}</span>
      </Button>
    </div>

    <p v-else class="mt-1.5 text-[11px] text-slate-400">{{ hint }}</p>
  </div>
</template>
