<script lang="ts">
import {markRaw} from 'vue'
import {IdCard} from 'lucide-vue-next'

export const fieldMeta = {type: 'grz', label: 'ГРЗ', icon: markRaw(IdCard)}
</script>

<script setup lang="ts">
import SimpleTextFieldSettings from './SimpleTextFieldSettings.vue'
import type {GrzBlockField} from '../../../lib/scenario-block-fields'

defineProps<{ field: GrzBlockField; disabled?: boolean }>()
const emit = defineEmits<{ update: [patch: Partial<GrzBlockField>] }>()
defineOptions({inheritAttrs: false})
</script>

<template>
  <SimpleTextFieldSettings :field="field" :disabled="disabled" @update="emit('update', $event)" />
</template>
