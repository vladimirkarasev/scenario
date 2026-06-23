<script setup lang="ts">
import { useSlots } from 'vue'

defineProps<{
    title?: string
    description?: string
}>()

const slots = useSlots()
</script>

<template>
    <section class="space-y-4">
        <header v-if="title || slots.header" class="space-y-0.5">
            <slot name="header">
                <div class="text-[13px] font-semibold text-slate-900">{{ title }}</div>
                <div v-if="description" class="text-[11px] text-slate-500">{{ description }}</div>
            </slot>
        </header>
        <div class="space-y-4">
            <slot />
        </div>
    </section>
</template>
