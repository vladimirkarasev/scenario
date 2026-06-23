<script setup>
import {cn} from '@/lib/utils'

const props = defineProps({
  class: {
    type: null,
    default: '',
  },
})
</script>

<template>
  <div
      data-slot="skeleton"
      :class="cn('animate-pulse rounded-md bg-muted/70', props.class)"
  />
</template>
