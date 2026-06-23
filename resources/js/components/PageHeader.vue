<script setup lang="ts">
defineProps<{
  title: string
  subtitle?: string
}>()
</script>

<template>
  <div class="mb-7 flex items-start justify-between gap-4">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">{{ title }}</h1>
      <p v-if="subtitle" class="mt-1 text-[13px] text-slate-500">{{ subtitle }}</p>
    </div>
    <div v-if="$slots.actions" class="flex shrink-0 items-center gap-2">
      <slot name="actions"/>
    </div>
  </div>
</template>
