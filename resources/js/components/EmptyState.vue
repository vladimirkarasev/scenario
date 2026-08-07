<script setup lang="ts">
defineProps<{
  title: string
  subtitle?: string
}>()
</script>

<template>
  <div class="flex flex-col items-center px-6 py-16 text-center">
    <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-muted text-muted-foreground">
      <slot name="icon" />
    </div>
    <div class="text-sm font-semibold text-foreground">{{ title }}</div>
    <div v-if="subtitle" class="mt-1 max-w-md text-xs text-muted-foreground">{{ subtitle }}</div>
    <div v-if="$slots.actions" class="mt-4">
      <slot name="actions" />
    </div>
  </div>
</template>
