<script setup lang="ts">
import { AlertCircle } from 'lucide-vue-next'

defineProps<{ message?: string | null }>()
</script>

<template>
    <div v-if="message" class="flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-3.5 py-2.5 text-[12px] text-red-700">
        <AlertCircle :size="14" class="mt-0.5 shrink-0" />
        <span>{{ message }}</span>
    </div>
</template>
