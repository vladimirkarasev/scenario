<script setup>
import {onBeforeUnmount, toRef, watch} from 'vue'
import {useEditor} from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Underline from '@tiptap/extension-underline'
import Link from '@tiptap/extension-link'
import Highlight from '@tiptap/extension-highlight'
import TextAlign from '@tiptap/extension-text-align'
import Placeholder from '@tiptap/extension-placeholder'
import Color from '@tiptap/extension-color'
import {TextStyle} from '@tiptap/extension-text-style'
import {Table} from '@tiptap/extension-table'
import {TableRow} from '@tiptap/extension-table-row'
import {TableHeader} from '@tiptap/extension-table-header'
import {TableCell} from '@tiptap/extension-table-cell'
import {Details} from '@/lib/tiptap-details'
import TiptapFormattingToolbar from '@/modules/scenario/components/tiptap/TiptapFormattingToolbar.vue'
import TiptapFormattingBubbleMenu from '@/modules/scenario/components/tiptap/TiptapFormattingBubbleMenu.vue'
import TiptapEditorContentArea from '@/modules/scenario/components/tiptap/TiptapEditorContentArea.vue'
import TiptapLinkDialog from '@/modules/scenario/components/tiptap/TiptapLinkDialog.vue'
import {useTiptapLinkDialog} from '@/modules/scenario/composables/useTiptapLinkDialog'

const props = defineProps({
  modelValue: {
    type: [Object, String, null],
    default: null,
  },
  placeholder: {
    type: String,
    default: 'Введите текст...',
  },
  editable: {
    type: Boolean,
    default: true,
  },
  minHeight: {
    type: String,
    default: 'min-h-48',
  },
  format: {
    type: String,
    default: 'json',
    validator: (value) => value === 'json' || value === 'html',
  },
})

const emit = defineEmits(['update:modelValue'])

const emptyDocument = {type: 'doc', content: [{type: 'paragraph'}]}

function isTiptapDocument(value) {
  return value && typeof value === 'object' && value.type === 'doc'
}

function normalizeContent(value) {
  if (isTiptapDocument(value)) {
    return value
  }

  if (typeof value !== 'string' || value.trim() === '') {
    return props.format === 'html' ? '' : emptyDocument
  }

  if (props.format === 'html') {
    return value
  }

  try {
    const parsed = JSON.parse(value)

    if (isTiptapDocument(parsed)) {
      return parsed
    }
  } catch {
    return value
  }

  return value
}

function getCurrentValue(nextEditor) {
  return props.format === 'html' ? nextEditor.getHTML() : nextEditor.getJSON()
}

function sameContent(left, right) {
  if (props.format === 'html') {
    const a = typeof left === 'string' ? left : ''
    const b = typeof right === 'string' ? right : ''
    return a === b
  }

  return JSON.stringify(normalizeContent(left)) === JSON.stringify(normalizeContent(right))
}

const editor = useEditor({
  content: normalizeContent(props.modelValue),
  editable: props.editable,
  extensions: [
    StarterKit.configure({
      heading: {levels: [1, 2, 3, 4, 5, 6]},
      link: false,
      underline: false,
    }),
    Underline,
    Link.configure({
      openOnClick: false,
      autolink: true,
      defaultProtocol: 'https',
    }),
    Highlight.configure({multicolor: true}),
    TextAlign.configure({types: ['heading', 'paragraph']}),
    Placeholder.configure({placeholder: props.placeholder}),
    TextStyle,
    Color,
    Details,
    Table.configure({resizable: true}),
    TableRow,
    TableHeader,
    TableCell,
  ],
  editorProps: {
    attributes: {
      class: 'prose prose-sm max-w-none focus:outline-none',
    },
  },
  onUpdate: ({editor: nextEditor}) => {
    emit('update:modelValue', getCurrentValue(nextEditor))
  },
})

watch(
    () => props.modelValue,
    (value) => {
      if (!editor.value || sameContent(value, getCurrentValue(editor.value))) {
        return
      }

      editor.value.commands.setContent(normalizeContent(value), false)
    },
)

watch(
    () => props.editable,
    (editable) => {
      editor.value?.setEditable(editable)
    },
)

onBeforeUnmount(() => {
  editor.value?.destroy()
})

const linkDialog = useTiptapLinkDialog(editor, toRef(props, 'editable'))
</script>

<template>
  <div class="rounded-2xl border border-slate-200 bg-white">
    <TiptapFormattingBubbleMenu :editor="editor" :editable="editable" plugin-key="textSelectionBubbleMenu"
                                 @open-link="linkDialog.openDialog"/>
    <TiptapFormattingToolbar :editor="editor" :editable="editable" @open-link="linkDialog.openDialog"/>
    <TiptapEditorContentArea :editor="editor" :editable="editable" :content-class="minHeight"/>
  </div>

  <TiptapLinkDialog
      v-model:open="linkDialog.open.value"
      :initial-url="linkDialog.url.value"
      :initial-target="linkDialog.target.value"
      :has-link="linkDialog.hasLink.value"
      @submit="linkDialog.apply"
      @remove="linkDialog.remove"
  />
</template>
