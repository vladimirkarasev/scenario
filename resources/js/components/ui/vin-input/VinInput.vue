<script setup lang="ts">
import {ref, watch, onMounted, onUnmounted} from 'vue'
import IMask from 'imask'
import type {VinShape} from '@/lib/vin-shape'

const props = withDefaults(defineProps<{
  modelValue: VinShape | string | null
  disabled?: boolean
  error?: boolean
}>(), {
  disabled: false,
  error: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: VinShape | null]
}>()

defineOptions({inheritAttrs: false})

function toValueString(v: VinShape | string | null | undefined): string {
  if (!v) return ''
  return typeof v === 'string' ? v : v.value
}

const VIN_MASK = /^[A-HJ-NPR-Z0-9]{0,17}$/

const inputRef = ref<HTMLInputElement>()
type ImaskInstance = ReturnType<typeof IMask>
let im: ImaskInstance | null = null

function applyMask(initialValue = '') {
  if (!inputRef.value) return
  im?.destroy()
  im = IMask(inputRef.value, {
    mask: VIN_MASK,
    prepare: (str: string) => str.toUpperCase(),
  })
  im.on('accept', handleAccept)
  if (initialValue) im.value = initialValue
}

function handleAccept() {
  if (!im) return
  const value = im.value
  emit('update:modelValue', value ? {value} : null)
}

onMounted(() => applyMask(toValueString(props.modelValue)))
onUnmounted(() => {
  im?.destroy()
  im = null
})

watch(() => props.modelValue, (val) => {
  if (!im) return
  const display = toValueString(val)
  if (im.value !== display) {
    im.value = display
  }
})
</script>

<template>
  <input
      ref="inputRef"
      v-bind="$attrs"
      :disabled="disabled"
      placeholder="XXXXXXXXXXXXXXXXX"
      class="flex h-9 w-full rounded-xl border bg-transparent px-2.5 py-1 text-base uppercase outline-none placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
      :class="[
          error
              ? 'border-destructive focus-visible:border-destructive focus-visible:ring-3 focus-visible:ring-destructive/30'
              : 'border-input focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50',
      ]"
  >
</template>
