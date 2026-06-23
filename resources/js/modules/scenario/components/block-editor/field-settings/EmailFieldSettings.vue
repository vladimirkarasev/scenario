<script lang="ts">
import { markRaw } from 'vue'
import { Mail } from 'lucide-vue-next'
export const fieldMeta = { type: 'email', label: 'Email', icon: markRaw(Mail) }
</script>

<script setup lang="ts">
import SimpleTextFieldSettings from './SimpleTextFieldSettings.vue'
import type { EmailBlockField } from '../../../lib/scenario-block-fields'

defineProps<{ field: EmailBlockField; disabled?: boolean }>()
const emit = defineEmits<{ update: [patch: Partial<EmailBlockField>] }>()
defineOptions({ inheritAttrs: false })
</script>

<template>
    <SimpleTextFieldSettings :field="field" :disabled="disabled" @update="emit('update', $event)" />
</template>
