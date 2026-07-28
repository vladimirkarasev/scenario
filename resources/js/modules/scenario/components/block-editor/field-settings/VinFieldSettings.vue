<script lang="ts">
import {markRaw} from 'vue'
import {Car} from 'lucide-vue-next'

export const fieldMeta = {type: 'vin', label: 'VIN', icon: markRaw(Car)}
</script>

<script setup lang="ts">
import SimpleTextFieldSettings from './SimpleTextFieldSettings.vue'
import type {VinBlockField} from '../../../lib/scenario-block-fields'

defineProps<{ field: VinBlockField; disabled?: boolean }>()
const emit = defineEmits<{ update: [patch: Partial<VinBlockField>] }>()
defineOptions({inheritAttrs: false})
</script>

<template>
  <SimpleTextFieldSettings :field="field" :disabled="disabled" @update="emit('update', $event)" />
</template>
