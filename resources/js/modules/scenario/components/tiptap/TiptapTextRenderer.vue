<script setup>
import {computed} from 'vue'
import {generateHTML} from '@tiptap/html'
import StarterKit from '@tiptap/starter-kit'
import Underline from '@tiptap/extension-underline'
import Link from '@tiptap/extension-link'
import Highlight from '@tiptap/extension-highlight'
import TextAlign from '@tiptap/extension-text-align'
import Color from '@tiptap/extension-color'
import {TextStyle} from '@tiptap/extension-text-style'
import {Table} from '@tiptap/extension-table'
import {TableRow} from '@tiptap/extension-table-row'
import {TableHeader} from '@tiptap/extension-table-header'
import {TableCell} from '@tiptap/extension-table-cell'
import {Details} from '@/lib/tiptap-details'

const props = defineProps({
  document: {
    type: [Object, String, null],
    default: null,
  },
  html: {
    type: String,
    default: '',
  },
})

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
  TextStyle,
  Color,
  Details,
  Table,
  TableRow,
  TableHeader,
  TableCell,
]

function isTiptapDocument(value) {
  return value && typeof value === 'object' && value.type === 'doc'
}

function pruneEmptyTextNodes(node) {
  if (!node || typeof node !== 'object') return node

  if (node.type === 'text') {
    return typeof node.text === 'string' && node.text.length > 0 ? node : null
  }

  if (Array.isArray(node.content)) {
    const content = node.content
        .map(pruneEmptyTextNodes)
        .filter((n) => n !== null)
    return {...node, content}
  }

  return node
}

function normalizeDocument(value) {
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
      return generateHTML(document, extensions)
    } catch (e) {
      console.error('TiptapTextRenderer: generateHTML failed', e, document)
      return props.html ?? ''
    }
  }

  return props.html ?? ''
})
</script>

<template>
  <div class="tiptap-content text-foreground/90" v-html="renderedHtml"/>
</template>
