<script lang="ts">
import { markRaw } from 'vue'
import { Phone } from 'lucide-vue-next'
export const fieldMeta = { type: 'phone', label: 'Телефон', icon: markRaw(Phone) }
</script>

<script setup lang="ts">
import SimpleTextFieldSettings from './SimpleTextFieldSettings.vue'
import type { PhoneBlockField } from '../../../lib/scenario-block-fields'

defineProps<{ field: PhoneBlockField; disabled?: boolean }>()
const emit = defineEmits<{ update: [patch: Partial<PhoneBlockField>] }>()
defineOptions({ inheritAttrs: false })
</script>

<template>
    <SimpleTextFieldSettings :field="field" :disabled="disabled" @update="emit('update', $event)" />
</template>
