<script setup lang="ts">
import {computed, onBeforeUnmount, ref} from 'vue'
import {Check, Copy} from 'lucide-vue-next'
import {Button} from '@/components/ui/button'
import {copyText, DEFAULT_COPY_STRATEGY, type CopyStrategy} from '@/lib/clipboard'

const props = withDefaults(defineProps<{
  text: string
  strategy?: CopyStrategy
  copied?: boolean
  duration?: number
  label?: string
  copiedLabel?: string
  title?: string
  disabled?: boolean
}>(), {
  strategy: DEFAULT_COPY_STRATEGY,
  copied: false,
  duration: 1500,
  label: '',
  copiedLabel: 'Скопировано',
  title: 'Скопировать',
  disabled: false,
})

const emit = defineEmits<{
  copied: []
  error: []
}>()

const locallyCopied = ref(false)
const resetTimer = ref<ReturnType<typeof setTimeout> | null>(null)
const isCopied = computed(() => props.copied || locallyCopied.value)
const buttonTitle = computed(() => isCopied.value ? props.copiedLabel : props.title)
const buttonSize = computed(() => props.label ? 'xs' : 'icon-xs')

async function copy(): Promise<void> {
  if (!await copyText(props.text, props.strategy)) {
    emit('error')
    return
  }

  locallyCopied.value = true
  emit('copied')

  if (resetTimer.value) {
    clearTimeout(resetTimer.value)
  }

  resetTimer.value = setTimeout(() => {
    locallyCopied.value = false
    resetTimer.value = null
  }, props.duration)
}

onBeforeUnmount(() => {
  if (resetTimer.value) {
    clearTimeout(resetTimer.value)
  }
})
</script>

<template>
  <Button
      type="button"
      variant="ghost"
      :size="buttonSize"
      :title="buttonTitle"
      :disabled="disabled"
      @click="copy"
  >
    <slot :copied="isCopied">
      <Check v-if="isCopied" class="text-emerald-500" />
      <Copy v-else />
      <span v-if="label">{{ isCopied ? copiedLabel : label }}</span>
    </slot>
  </Button>
</template>
