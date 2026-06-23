<script setup lang="ts">
defineProps<{
    title: string
    subtitle?: string
}>()
</script>

<template>
    <div class="flex flex-col items-center py-16 text-center">
        <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-slate-400">
            <slot name="icon" />
        </div>
        <div class="text-[13px] font-semibold text-slate-900">{{ title }}</div>
        <div v-if="subtitle" class="mt-1 text-[12px] text-slate-400">{{ subtitle }}</div>
    </div>
</template>
