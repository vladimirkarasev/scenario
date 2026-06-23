<script setup lang="ts">
import {computed} from 'vue'

const props = withDefaults(defineProps<{
  cols?: 1 | 2 | 3
}>(), {
  cols: 2,
})

const colsClass = computed(() => {
  if (props.cols === 3) return 'md:grid-cols-3'
  if (props.cols === 1) return 'md:grid-cols-1'
  return 'md:grid-cols-2'
})
</script>

<template>
  <div class="grid gap-4" :class="colsClass">
    <slot/>
  </div>
</template>
