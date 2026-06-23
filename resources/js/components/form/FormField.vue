<script setup lang="ts">
import { Label } from '@/components/ui/label'

withDefaults(defineProps<{
    label?: string
    hint?: string
    error?: string
    required?: boolean
    for?: string
}>(), {
    required: false,
})
</script>

<template>
    <div class="space-y-1.5">
        <Label v-if="label" :for="$props.for" class="text-[12px] font-semibold text-slate-700">
            {{ label }}
            <span v-if="required" class="text-red-500">*</span>
        </Label>
        <slot />
        <p v-if="hint && !error" class="text-[11px] text-muted-foreground">{{ hint }}</p>
        <p v-if="error" class="text-[11px] text-red-600">{{ error }}</p>
    </div>
</template>
