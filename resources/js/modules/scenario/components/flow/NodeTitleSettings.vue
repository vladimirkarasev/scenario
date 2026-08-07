<script setup lang="ts">
import {computed} from 'vue'
import {FormCheckbox, FormInput} from '@/components/form'

const props = defineProps<{
  title?: unknown
  hideTitle?: unknown
  editable?: boolean
}>()

const emit = defineEmits<{
  'update:title': [value: string]
  'update:hideTitle': [value: boolean]
  change: []
}>()

const normalizedTitle = computed(() => String(props.title ?? ''))
const normalizedHideTitle = computed(() => Boolean(props.hideTitle ?? true))
const canEditTitle = computed(() => props.editable === true)

function updateTitle(value: string): void {
  emit('update:title', value)
  emit('change')
}

function updateHideTitle(value: boolean): void {
  emit('update:hideTitle', value)
  emit('change')
}
</script>

<template>
  <div class="grid items-start gap-3 sm:grid-cols-[minmax(0,1fr)_auto]">
    <FormInput
        :model-value="normalizedTitle"
        name="node-title"
        label="Заголовок"
        placeholder="Название ноды"
        :disabled="!canEditTitle"
        @update:model-value="updateTitle"
    />
    <FormCheckbox
        :model-value="normalizedHideTitle"
        name="node-hide-title"
        label="Скрыть заголовок"
        class="sm:mt-6 sm:min-w-44"
        :disabled="!editable"
        @update:model-value="updateHideTitle"
    />
  </div>
</template>
