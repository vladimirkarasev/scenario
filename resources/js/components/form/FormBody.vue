<script setup lang="ts">
import {computed} from 'vue'

const props = withDefaults(defineProps<{
  padding?: 'sm' | 'md' | 'lg'
  spacing?: 'sm' | 'md' | 'lg'
  scrollable?: boolean
}>(), {
  padding: 'md',
  spacing: 'md',
  scrollable: true,
})

const paddingClass = computed(() => ({sm: 'px-4 py-4', md: 'px-6 py-5', lg: 'px-8 py-6'}[props.padding]))
const spacingClass = computed(() => ({sm: 'space-y-3', md: 'space-y-5', lg: 'space-y-6'}[props.spacing]))
const scrollClass = computed(() => props.scrollable ? 'flex-1 overflow-y-auto' : '')
</script>

<template>
  <div :class="[paddingClass, spacingClass, scrollClass]">
    <slot/>
  </div>
</template>
