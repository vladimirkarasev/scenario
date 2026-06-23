<script lang="ts">
import {markRaw} from 'vue'
import {AlignJustify} from 'lucide-vue-next'

export const fieldMeta = {type: 'input', label: 'Текст', icon: markRaw(AlignJustify)}
</script>

<script setup lang="ts">
import SimpleTextFieldSettings from './SimpleTextFieldSettings.vue'
import type {InputBlockField} from '../../../lib/scenario-block-fields'

defineProps<{ field: InputBlockField; disabled?: boolean }>()
const emit = defineEmits<{ update: [patch: Partial<InputBlockField>] }>()
defineOptions({inheritAttrs: false})
</script>

<template>
  <SimpleTextFieldSettings :field="field" :disabled="disabled" @update="emit('update', $event)"/>
</template>
