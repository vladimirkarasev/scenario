<script setup lang="ts">
import {X} from 'lucide-vue-next'

withDefaults(defineProps<{
  label: string
  removable?: boolean
}>(), {
  removable: true,
})

const emit = defineEmits<{
  remove: []
}>()
</script>

<template>
  <span
      :title="label"
      class="inline-flex max-w-full items-start gap-1 rounded-md bg-slate-100 py-0.5 pl-2 pr-1 text-xs font-medium text-slate-700"
  >
    <span class="min-w-0 whitespace-normal break-words leading-4 [overflow-wrap:anywhere]">{{ label }}</span>
    <span
        v-if="removable"
        role="button"
        tabindex="0"
        aria-label="Удалить"
        class="mt-px inline-flex size-3.5 shrink-0 cursor-pointer items-center justify-center rounded text-slate-400 transition hover:bg-slate-200 hover:text-slate-700 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
        @click.stop="emit('remove')"
        @keydown.enter.stop.prevent="emit('remove')"
        @keydown.space.stop.prevent="emit('remove')"
    >
      <X class="size-3" />
    </span>
  </span>
</template>
