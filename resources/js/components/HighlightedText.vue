<script setup lang="ts">
import {computed} from 'vue'

const props = defineProps<{
  text: string
  query: string
}>()

interface TextPart {
  text: string
  highlighted: boolean
}

const parts = computed<TextPart[]>(() => {
  const words = props.query.trim().split(/\s+/).filter(Boolean)
  if (words.length === 0) return [{text: props.text, highlighted: false}]

  const escapedWords = words.map(word => word.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'))
  const expression = new RegExp(`(${escapedWords.join('|')})`, 'gi')

  return props.text
      .split(expression)
      .filter(text => text !== '')
      .map(text => ({
        text,
        highlighted: words.some(word => word.localeCompare(text, undefined, {sensitivity: 'accent'}) === 0),
      }))
})
</script>

<template>
  <span>
    <template v-for="(part, index) in parts" :key="`${index}-${part.text}`">
      <mark
          v-if="part.highlighted"
          class="rounded-[3px] bg-amber-200 text-amber-900 not-italic"
      >{{ part.text }}</mark>
      <template v-else>{{ part.text }}</template>
    </template>
  </span>
</template>
