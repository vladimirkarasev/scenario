<script lang="ts">
import { markRaw } from 'vue'
import { Type } from 'lucide-vue-next'
export const fieldMeta = { type: 'rich_text', label: 'Редактор', icon: markRaw(Type) }
</script>

<script setup lang="ts">
defineOptions({ inheritAttrs: false })
</script>

<template>
    <div />
</template>
