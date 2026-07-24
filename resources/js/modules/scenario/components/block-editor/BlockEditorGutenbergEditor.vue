<script setup lang="ts">
import {onBeforeUnmount, toRef, watch} from 'vue'
import {useEditor} from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Underline from '@tiptap/extension-underline'
import Link from '@tiptap/extension-link'
import Highlight from '@tiptap/extension-highlight'
import TextAlign from '@tiptap/extension-text-align'
import Placeholder from '@tiptap/extension-placeholder'
import Color from '@tiptap/extension-color'
import {Table} from '@tiptap/extension-table'
import {TableRow} from '@tiptap/extension-table-row'
import {TableHeader} from '@tiptap/extension-table-header'
import {TableCell} from '@tiptap/extension-table-cell'
import {FontSize} from '@/lib/tiptap-font-size'
import {Details} from '@/lib/tiptap-details'
import {ScenarioField} from '@/lib/tiptap-scenario-field'
import {useTiptapFormatting} from '@/modules/scenario/composables/useTiptapFormatting'
import {useTiptapLinkDialog} from '@/modules/scenario/composables/useTiptapLinkDialog'
import TiptapFormattingToolbar from '@/modules/scenario/components/tiptap/TiptapFormattingToolbar.vue'
import TiptapFormattingBubbleMenu from '@/modules/scenario/components/tiptap/TiptapFormattingBubbleMenu.vue'
import TiptapEditorContentArea from '@/modules/scenario/components/tiptap/TiptapEditorContentArea.vue'
import TiptapLinkDialog from '@/modules/scenario/components/tiptap/TiptapLinkDialog.vue'
import type {BlockField, BlockFieldType} from '@/modules/scenario/lib/scenario-block-fields'
import type {Component} from 'vue'

const props = defineProps<{
  modelValue: unknown
  fields: BlockField[]
  canEdit: boolean
  fieldTypeLabel: (type: BlockFieldType) => string
  fieldTypeIcon: (type: BlockFieldType) => Component
}>()

const emit = defineEmits<{
  'update:modelValue': [unknown]
  'update-field': [fieldId: string, patch: Partial<BlockField>]
  'open-settings': [fieldId: string]
  'delete-field': [fieldId: string]
  'reorder-fields': [orderedIds: string[]]
}>()

type TiptapDoc = { type: 'doc'; content: Record<string, unknown>[] }

function isTiptapDoc(value: unknown): value is TiptapDoc {
  return Boolean(value && typeof value === 'object' && (value as {type?: string}).type === 'doc')
}

function fieldNode(field: BlockField): Record<string, unknown> {
  const marks: Record<string, unknown>[] = []
  if (field.labelFontSize || field.labelColor) {
    marks.push({
      type: 'textStyle',
      attrs: {fontSize: field.labelFontSize ?? null, color: field.labelColor ?? null},
    })
  }
  if (field.labelHighlight) {
    marks.push({type: 'highlight', attrs: {color: field.labelHighlight}})
  }

  return {
    type: 'scenarioField',
    attrs: {fieldId: field.id},
    content: field.label ? [{type: 'text', text: field.label, ...(marks.length ? {marks} : {})}] : [],
  }
}

function docFieldIds(doc: TiptapDoc): string[] {
  return doc.content
      .filter((node) => node.type === 'scenarioField')
      .map((node) => String((node.attrs as {fieldId?: string} | undefined)?.fieldId ?? ''))
      .filter(Boolean)
}

interface LabelInfo {
  text: string
  fontSize: string | null
  color: string | null
  highlight: string | null
}

function fieldNodeLabelInfo(node: Record<string, unknown>): LabelInfo {
  const content = Array.isArray(node.content) ? node.content as Record<string, unknown>[] : []
  const info: LabelInfo = {text: '', fontSize: null, color: null, highlight: null}

  for (const n of content) {
    if (n.type !== 'text') continue
    info.text += String(n.text ?? '')

    const marks = Array.isArray(n.marks) ? n.marks as Record<string, unknown>[] : []

    if (info.fontSize === null) {
      const value = (marks.find((m) => m.type === 'textStyle')?.attrs as Record<string, unknown> | undefined)?.fontSize
      if (typeof value === 'string' && value) info.fontSize = value
    }

    if (info.color === null) {
      const value = (marks.find((m) => m.type === 'textStyle')?.attrs as Record<string, unknown> | undefined)?.color
      if (typeof value === 'string' && value) info.color = value
    }

    if (info.highlight === null) {
      const value = (marks.find((m) => m.type === 'highlight')?.attrs as Record<string, unknown> | undefined)?.color
      if (typeof value === 'string' && value) info.highlight = value
    }
  }

  return info
}

function arraysEqual(a: string[], b: string[]): boolean {
  return a.length === b.length && a.every((id, i) => id === b[i])
}

function buildInitialContent(): TiptapDoc {
  const base: TiptapDoc = isTiptapDoc(props.modelValue)
      ? {type: 'doc', content: [...props.modelValue.content]}
      : {type: 'doc', content: [...props.fields.map(fieldNode), {type: 'paragraph'}]}

  const present = new Set(docFieldIds(base))
  const missing = props.fields.filter((f) => !present.has(f.id))
  if (missing.length) {
    base.content = [...base.content, ...missing.map(fieldNode)]
  }
  if (!base.content.length) {
    base.content = [{type: 'paragraph'}]
  }

  return base
}

const editor = useEditor({
  content: buildInitialContent(),
  editable: props.canEdit,
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
    Placeholder.configure({placeholder: 'Заголовок, описание шага... добавляй поля из панели слева'}),
    FontSize,
    Color,
    Details,
    Table.configure({resizable: true}),
    TableRow,
    TableHeader,
    TableCell,
    ScenarioField.configure({
      getField: (id: string) => props.fields.find((f) => f.id === id) ?? null,
      isEditable: () => props.canEdit,
      fieldTypeLabel: props.fieldTypeLabel,
      fieldTypeIcon: props.fieldTypeIcon,
      onUpdateField: (id: string, patch: Partial<BlockField>) => emit('update-field', id, patch),
      onOpenSettings: (id: string) => emit('open-settings', id),
      onDeleteField: (id: string) => emit('delete-field', id),
    }),
  ],
  editorProps: {
    attributes: {
      class: 'prose prose-sm max-w-none focus:outline-none',
    },
  },
  onUpdate: ({editor: nextEditor}) => {
    const json = nextEditor.getJSON() as TiptapDoc
    emit('update:modelValue', json)

    const fieldNodes = json.content.filter((node) => node.type === 'scenarioField')

    fieldNodes.forEach((node) => {
      const id = String((node.attrs as {fieldId?: string} | undefined)?.fieldId ?? '')
      const currentField = props.fields.find((f) => f.id === id)
      if (!currentField) return

      const info = fieldNodeLabelInfo(node)
      const labelFontSize = info.fontSize ?? undefined
      const labelColor = info.color ?? undefined
      const labelHighlight = info.highlight ?? undefined
      const patch: Partial<BlockField> = {}
      if (info.text !== currentField.label) patch.label = info.text
      if (labelFontSize !== currentField.labelFontSize) patch.labelFontSize = labelFontSize
      if (labelColor !== currentField.labelColor) patch.labelColor = labelColor
      if (labelHighlight !== currentField.labelHighlight) patch.labelHighlight = labelHighlight
      if (Object.keys(patch).length) emit('update-field', id, patch)
    })

    const nextOrder = fieldNodes
        .map((node) => String((node.attrs as {fieldId?: string} | undefined)?.fieldId ?? ''))
        .filter(Boolean)
    const currentOrder = props.fields.map((f) => f.id).filter((id) => nextOrder.includes(id))
    if (nextOrder.length === currentOrder.length && !arraysEqual(nextOrder, currentOrder)) {
      emit('reorder-fields', nextOrder)
    }
  },
})

watch(
    () => props.fields.map((f) => f.id).join(','),
    () => {
      if (!editor.value) return
      const currentIds = new Set(docFieldIds(editor.value.getJSON() as TiptapDoc))
      const missing = props.fields.filter((f) => !currentIds.has(f.id))
      if (!missing.length) return

      const endPos = editor.value.state.doc.content.size
      editor.value.chain().insertContentAt(endPos, missing.map(fieldNode)).run()
    },
)

watch(
    () => props.canEdit,
    (value) => {
      editor.value?.setEditable(value)
    },
)

const formatting = useTiptapFormatting(editor, toRef(props, 'canEdit'))
const linkDialog = useTiptapLinkDialog(editor, toRef(props, 'canEdit'))

function currentFontSizeNumber(): string {
  const raw = String(editor.value?.getAttributes('textStyle')?.fontSize ?? '')
  const match = raw.match(/^(\d+(?:\.\d+)?)/)
  return match ? match[1] : ''
}

function setFontSize(e: Event) {
  const raw = (e.target as HTMLInputElement).value.trim()
  const n = Number(raw)
  if (!raw || !Number.isFinite(n) || n <= 0) {
    formatting.run((chain) => chain.unsetFontSize().run())
    return
  }
  formatting.run((chain) => chain.setFontSize(`${n}px`).run())
}

onBeforeUnmount(() => {
  editor.value?.destroy()
})
</script>

<template>
  <div class="rounded-2xl border border-slate-200 bg-white">
    <TiptapFormattingBubbleMenu :editor="editor" :editable="canEdit" plugin-key="gutenbergTextSelectionBubbleMenu"
                                 @open-link="linkDialog.openDialog">
      <template #extra>
        <label class="relative inline-flex h-8 w-16 items-center" title="Размер текста, px">
          <input
              type="number"
              min="1"
              max="200"
              list="scenario-gutenberg-font-size-presets"
              placeholder="Разм."
              class="h-8 w-full rounded-md border-0 bg-transparent px-2 text-sm font-medium text-slate-900 outline-none [appearance:textfield] hover:bg-slate-100 focus:bg-slate-100 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
              :value="currentFontSizeNumber()"
              @change="setFontSize"
          >
          <datalist id="scenario-gutenberg-font-size-presets">
            <option value="12"/>
            <option value="14"/>
            <option value="16"/>
            <option value="18"/>
            <option value="24"/>
            <option value="32"/>
            <option value="48"/>
          </datalist>
        </label>
      </template>
    </TiptapFormattingBubbleMenu>

    <TiptapFormattingToolbar :editor="editor" :editable="canEdit" @open-link="linkDialog.openDialog"/>

    <TiptapEditorContentArea :editor="editor" :editable="canEdit"/>
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
