<script setup lang="ts">
import {computed} from 'vue'
import {generateHTML} from '@tiptap/html'
import type {JSONContent} from '@tiptap/core'
import StarterKit from '@tiptap/starter-kit'
import Underline from '@tiptap/extension-underline'
import Link from '@tiptap/extension-link'
import Highlight from '@tiptap/extension-highlight'
import TextAlign from '@tiptap/extension-text-align'
import Color from '@tiptap/extension-color'
import {Table} from '@tiptap/extension-table'
import {TableRow} from '@tiptap/extension-table-row'
import {TableHeader} from '@tiptap/extension-table-header'
import {TableCell} from '@tiptap/extension-table-cell'
import {Details} from '@/lib/tiptap-details'
import {FontSize} from '@/lib/tiptap-font-size'
import {sanitizeHtml} from '@/lib/safe-html'

const props = defineProps<{
  document?: unknown
  html?: string
}>()

const extensions = [
  StarterKit.configure({
    heading: {levels: [1, 2, 3, 4, 5, 6]},
    link: false,
    underline: false,
  }),
  Underline,
  Link,
  Highlight.configure({multicolor: true}),
  TextAlign.configure({types: ['heading', 'paragraph']}),
  FontSize,
  Color,
  Details,
  Table,
  TableRow,
  TableHeader,
  TableCell,
]

function isTiptapDocument(value: unknown): value is JSONContent {
  return typeof value === 'object' && value !== null && 'type' in value && value.type === 'doc'
}

function pruneEmptyTextNodes(node: JSONContent): JSONContent | null {
  if (node.type === 'text') {
    return typeof node.text === 'string' && node.text.length > 0 ? node : null
  }

  if (node.content) {
    const content = node.content
        .map(pruneEmptyTextNodes)
        .filter((item): item is JSONContent => item !== null)
    return {...node, content}
  }

  return node
}

function normalizeDocument(value: unknown): JSONContent | null {
  if (isTiptapDocument(value)) {
    return pruneEmptyTextNodes(value)
  }

  if (typeof value !== 'string' || value.trim() === '') {
    return null
  }

  try {
    const parsed = JSON.parse(value)

    return isTiptapDocument(parsed) ? pruneEmptyTextNodes(parsed) : null
  } catch {
    return null
  }
}

const renderedHtml = computed(() => {
  const document = normalizeDocument(props.document)

  if (document) {
    try {
      return sanitizeHtml(generateHTML(document, extensions))
    } catch {
      return sanitizeHtml(props.html ?? '')
    }
  }

  return sanitizeHtml(props.html ?? '')
})
</script>

<template>
  <div class="tiptap-content text-foreground/90" v-html="renderedHtml" />
</template>
