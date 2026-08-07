<script setup lang="ts">
withDefaults(defineProps<{
  contentClass?: string
}>(), {
  contentClass: '',
})
</script>

<template>
  <div class="flex h-full w-full flex-col">
    <slot name="bars" />
    <div class="flex-1 overflow-auto" :class="contentClass">
      <slot/>
    </div>
  </div>
</template>
