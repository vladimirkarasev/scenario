<script setup>
import {onBeforeUnmount, watch} from 'vue'
import {EditorContent, useEditor} from '@tiptap/vue-3'
import {BubbleMenu} from '@tiptap/vue-3/menus'
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
import {Button} from '@/components/ui/button'
import {
  ContextMenu,
  ContextMenuContent,
  ContextMenuItem,
  ContextMenuSeparator,
  ContextMenuTrigger,
} from '@/components/ui/context-menu'
import {
  AlignCenter,
  AlignJustify,
  AlignLeft,
  AlignRight,
  Bold,
  ChevronDown,
  Code,
  Highlighter,
  Italic,
  Link2,
  List,
  ListCollapse,
  ListOrdered,
  Minus,
  Palette,
  Quote,
  Redo2,
  RemoveFormatting,
  Strikethrough,
  Table as TableIcon,
  Underline as UnderlineIcon,
  Undo2,
  Unlink,
} from 'lucide-vue-next'

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

function isActive(name, attrs = {}) {
  return editor.value?.isActive(name, attrs) ?? false
}

function run(command) {
  if (!editor.value || !props.editable) {
    return
  }

  command(editor.value.chain().focus()).run()
}

function setLink() {
  if (!editor.value || !props.editable) {
    return
  }

  const previousUrl = editor.value.getAttributes('link').href ?? ''
  const url = window.prompt('URL', previousUrl)

  if (url === null) {
    return
  }

  if (url.trim() === '') {
    editor.value.chain().focus().extendMarkRange('link').unsetLink().run()
    return
  }

  editor.value.chain().focus().extendMarkRange('link').setLink({href: url.trim()}).run()
}

function toolbarButtonClass(active = false) {
  return active ? 'bg-slate-100 text-slate-950 ring-1 ring-slate-200' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-950'
}

function bubbleButtonClass(active = false) {
  return active ? 'bg-slate-100 text-slate-950' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-950'
}

function currentBlockType() {
  for (const level of [1, 2, 3, 4, 5, 6]) {
    if (isActive('heading', {level})) {
      return `heading${level}`
    }
  }

  return 'paragraph'
}

function setBlockType(e) {
  const value = e.target.value
  const match = value.match(/^heading(\d)$/)

  if (match) {
    run((chain) => chain.toggleHeading({level: Number(match[1])}))
    return
  }

  run((chain) => chain.setParagraph())
}

function currentColor() {
  return editor.value?.getAttributes('textStyle')?.color ?? '#000000'
}

function setColor(e) {
  run((chain) => chain.setColor(e.target.value))
}

function currentHighlightColor() {
  return editor.value?.getAttributes('highlight')?.color ?? '#fef08a'
}

function setHighlightColor(e) {
  run((chain) => chain.setHighlight({color: e.target.value}))
}


function shouldShowBubbleMenu({editor: bubbleEditor, from, to}) {
  return props.editable && bubbleEditor.isEditable && from !== to && bubbleEditor.state.doc.textBetween(from, to).trim().length > 0
}

const bubbleMenuOptions = {
  placement: 'top',
  offset: 10,
  flip: true,
  shift: {padding: 8},
}

onBeforeUnmount(() => {
  editor.value?.destroy()
})
</script>

<template>
  <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
    <BubbleMenu
        v-if="editor"
        :editor="editor"
        :options="bubbleMenuOptions"
        :should-show="shouldShowBubbleMenu"
        :update-delay="80"
        plugin-key="textSelectionBubbleMenu"
    >
      <div
          class="flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-2 py-1.5 text-slate-900 shadow-xl shadow-slate-950/15">
        <label class="relative inline-flex h-8 w-20 items-center">
          <select
              class="h-8 w-full appearance-none rounded-md border-0 bg-transparent px-2 pr-8 text-sm font-medium text-slate-900 outline-none hover:bg-slate-100 focus:bg-slate-100"
              style="background-image: none;"
              :value="currentBlockType()"
              @change="setBlockType"
          >
            <option class="bg-white text-slate-900" value="paragraph">T</option>
            <option class="bg-white text-slate-900" value="heading1">H1</option>
            <option class="bg-white text-slate-900" value="heading2">H2</option>
            <option class="bg-white text-slate-900" value="heading3">H3</option>
            <option class="bg-white text-slate-900" value="heading4">H4</option>
            <option class="bg-white text-slate-900" value="heading5">H5</option>
            <option class="bg-white text-slate-900" value="heading6">H6</option>
          </select>
          <ChevronDown class="pointer-events-none absolute right-2 size-4 text-slate-500"/>
        </label>
        <span class="mx-1 h-6 w-px bg-slate-200"/>
        <button type="button" class="inline-flex size-8 items-center justify-center rounded-md transition"
                :class="bubbleButtonClass(isActive('bold'))" @click="run((chain) => chain.toggleBold())">
          <Bold class="size-4"/>
        </button>
        <button type="button" class="inline-flex size-8 items-center justify-center rounded-md transition"
                :class="bubbleButtonClass(isActive('italic'))" @click="run((chain) => chain.toggleItalic())">
          <Italic class="size-4"/>
        </button>
        <button type="button" class="inline-flex size-8 items-center justify-center rounded-md transition"
                :class="bubbleButtonClass(isActive('underline'))" @click="run((chain) => chain.toggleUnderline())">
          <UnderlineIcon class="size-4"/>
        </button>
        <button type="button" class="inline-flex size-8 items-center justify-center rounded-md transition"
                :class="bubbleButtonClass(isActive('strike'))" @click="run((chain) => chain.toggleStrike())">
          <Strikethrough class="size-4"/>
        </button>
        <label
            class="inline-flex size-8 cursor-pointer items-center justify-center rounded-md transition"
            :class="bubbleButtonClass(isActive('highlight'))"
            title="Цвет выделения"
        >
          <Highlighter class="size-4"/>
          <input class="sr-only" type="color" :value="currentHighlightColor()" @input="setHighlightColor"/>
        </label>
        <button type="button" class="inline-flex size-8 items-center justify-center rounded-md transition"
                :class="bubbleButtonClass(isActive('code'))" @click="run((chain) => chain.toggleCode())">
          <Code class="size-4"/>
        </button>
        <span class="mx-1 h-6 w-px bg-slate-200"/>
        <label
            class="inline-flex size-8 cursor-pointer items-center justify-center rounded-md text-slate-700 transition hover:bg-slate-100 hover:text-slate-950"
            title="Цвет текста">
          <Palette class="size-4"/>
          <input class="sr-only" type="color" :value="currentColor()" @input="setColor"/>
        </label>
        <button type="button" class="inline-flex size-8 items-center justify-center rounded-md transition"
                :class="bubbleButtonClass(isActive('link'))" @click="setLink">
          <Link2 class="size-4"/>
        </button>
      </div>
    </BubbleMenu>

    <div class="flex flex-wrap items-center gap-0 border-b border-slate-200 bg-white px-1.5 py-1">
      <Button type="button" variant="ghost" size="icon-sm" class="size-7 shrink-0" :class="toolbarButtonClass(false)"
              :disabled="!editable || !editor?.can().undo()" title="Отменить" @click="run((chain) => chain.undo())">
        <Undo2 class="size-4"/>
      </Button>
      <Button type="button" variant="ghost" size="icon-sm" class="size-7 shrink-0" :class="toolbarButtonClass(false)"
              :disabled="!editable || !editor?.can().redo()" title="Повторить" @click="run((chain) => chain.redo())">
        <Redo2 class="size-4"/>
      </Button>

      <span class="mx-1 h-5 w-px shrink-0 bg-slate-200"/>

      <Button type="button" variant="ghost" size="icon-sm" class="size-7 shrink-0"
              :class="toolbarButtonClass(isActive('bold'))" :disabled="!editable" title="Жирный"
              @click="run((chain) => chain.toggleBold())">
        <Bold class="size-4"/>
      </Button>
      <Button type="button" variant="ghost" size="icon-sm" class="size-7 shrink-0"
              :class="toolbarButtonClass(isActive('italic'))" :disabled="!editable" title="Курсив"
              @click="run((chain) => chain.toggleItalic())">
        <Italic class="size-4"/>
      </Button>
      <Button type="button" variant="ghost" size="icon-sm" class="size-7 shrink-0"
              :class="toolbarButtonClass(isActive('underline'))" :disabled="!editable" title="Подчеркнуть"
              @click="run((chain) => chain.toggleUnderline())">
        <UnderlineIcon class="size-4"/>
      </Button>
      <Button type="button" variant="ghost" size="icon-sm" class="size-7 shrink-0"
              :class="toolbarButtonClass(isActive('strike'))" :disabled="!editable" title="Зачеркнуть"
              @click="run((chain) => chain.toggleStrike())">
        <Strikethrough class="size-4"/>
      </Button>
      <label
          class="inline-flex size-7 shrink-0 cursor-pointer items-center justify-center rounded-md transition"
          :class="[toolbarButtonClass(isActive('highlight')), { 'pointer-events-none opacity-50': !editable }]"
          title="Цвет выделения"
      >
        <Highlighter class="size-4"/>
        <input class="sr-only" type="color" :value="currentHighlightColor()" :disabled="!editable"
               @input="setHighlightColor"/>
      </label>
      <Button type="button" variant="ghost" size="icon-sm" class="size-7 shrink-0"
              :class="toolbarButtonClass(isActive('code'))" :disabled="!editable" title="Код"
              @click="run((chain) => chain.toggleCode())">
        <Code class="size-4"/>
      </Button>

      <span class="mx-1 h-5 w-px shrink-0 bg-slate-200"/>

      <label
          class="relative inline-flex h-7 w-9 shrink-0 items-center rounded-md border transition"
          :class="isActive('heading') ? 'border-sky-500 bg-sky-50 text-slate-950' : 'border-transparent text-slate-700 hover:bg-slate-100 hover:text-slate-950'"
          title="Стиль блока"
      >
        <select
            class="h-full w-full appearance-none rounded-md border-0 bg-transparent p-0 pl-1.5 text-xs font-semibold outline-none"
            style="background-image: none;"
            :value="currentBlockType()"
            :disabled="!editable"
            @change="setBlockType"
        >
          <option class="bg-white text-slate-900" value="paragraph">T</option>
          <option class="bg-white text-slate-900" value="heading1">H1</option>
          <option class="bg-white text-slate-900" value="heading2">H2</option>
          <option class="bg-white text-slate-900" value="heading3">H3</option>
          <option class="bg-white text-slate-900" value="heading4">H4</option>
          <option class="bg-white text-slate-900" value="heading5">H5</option>
          <option class="bg-white text-slate-900" value="heading6">H6</option>
        </select>
        <ChevronDown class="pointer-events-none absolute right-1 size-3 text-slate-500"/>
      </label>

      <Button type="button" variant="ghost" size="icon-sm" class="size-7 shrink-0"
              :class="toolbarButtonClass(isActive('bulletList'))" :disabled="!editable" title="Маркированный список"
              @click="run((chain) => chain.toggleBulletList())">
        <List class="size-4"/>
      </Button>
      <Button type="button" variant="ghost" size="icon-sm" class="size-7 shrink-0"
              :class="toolbarButtonClass(isActive('orderedList'))" :disabled="!editable" title="Нумерованный список"
              @click="run((chain) => chain.toggleOrderedList())">
        <ListOrdered class="size-4"/>
      </Button>
      <Button type="button" variant="ghost" size="icon-sm" class="size-7 shrink-0"
              :class="toolbarButtonClass(isActive('details'))" :disabled="!editable" title="Раскрывающийся блок"
              @click="run((chain) => chain.setDetails())">
        <ListCollapse class="size-4"/>
      </Button>

      <span class="mx-1 h-5 w-px shrink-0 bg-slate-200"/>

      <label
          class="inline-flex h-7 shrink-0 cursor-pointer items-center justify-center gap-0.5 rounded-md px-1 transition"
          :class="{ 'pointer-events-none opacity-50': !editable }"
          title="Цвет текста"
      >
        <Palette class="size-4 text-slate-700"/>
        <ChevronDown class="size-3 text-slate-500"/>
        <input class="sr-only" type="color" :value="currentColor()" :disabled="!editable" @input="setColor"/>
      </label>

      <Button type="button" variant="ghost" size="icon-sm" class="size-7 shrink-0"
              :class="toolbarButtonClass(isActive('link'))" :disabled="!editable" title="Ссылка" @click="setLink">
        <Link2 class="size-4"/>
      </Button>
      <Button type="button" variant="ghost" size="icon-sm" class="size-7 shrink-0" :class="toolbarButtonClass(false)"
              :disabled="!editable" title="Убрать ссылку" @click="run((chain) => chain.unsetLink())">
        <Unlink class="size-4"/>
      </Button>

      <span class="mx-1 h-5 w-px shrink-0 bg-slate-200"/>

      <Button type="button" variant="ghost" size="icon-sm" class="size-7 shrink-0"
              :class="toolbarButtonClass(isActive('blockquote'))" :disabled="!editable" title="Цитата"
              @click="run((chain) => chain.toggleBlockquote())">
        <Quote class="size-4"/>
      </Button>
      <Button type="button" variant="ghost" size="icon-sm" class="size-7 shrink-0" :class="toolbarButtonClass(false)"
              :disabled="!editable" title="Горизонтальная линия" @click="run((chain) => chain.setHorizontalRule())">
        <Minus class="size-4"/>
      </Button>

      <span class="mx-1 h-5 w-px shrink-0 bg-slate-200"/>

      <Button type="button" variant="ghost" size="icon-sm" class="size-7 shrink-0"
              :class="toolbarButtonClass(isActive('table'))" :disabled="!editable"
              title="Вставить таблицу (правый клик в таблице — управление)"
              @click="run((chain) => chain.insertTable({ rows: 3, cols: 3, withHeaderRow: true }))">
        <TableIcon class="size-4"/>
      </Button>

      <span class="mx-1 h-5 w-px shrink-0 bg-slate-200"/>

      <Button type="button" variant="ghost" size="icon-sm" class="size-7 shrink-0"
              :class="toolbarButtonClass(isActive({ textAlign: 'left' }))" :disabled="!editable" title="По левому краю"
              @click="run((chain) => chain.setTextAlign('left'))">
        <AlignLeft class="size-4"/>
      </Button>
      <Button type="button" variant="ghost" size="icon-sm" class="size-7 shrink-0"
              :class="toolbarButtonClass(isActive({ textAlign: 'center' }))" :disabled="!editable" title="По центру"
              @click="run((chain) => chain.setTextAlign('center'))">
        <AlignCenter class="size-4"/>
      </Button>
      <Button type="button" variant="ghost" size="icon-sm" class="size-7 shrink-0"
              :class="toolbarButtonClass(isActive({ textAlign: 'right' }))" :disabled="!editable"
              title="По правому краю" @click="run((chain) => chain.setTextAlign('right'))">
        <AlignRight class="size-4"/>
      </Button>
      <Button type="button" variant="ghost" size="icon-sm" class="size-7 shrink-0"
              :class="toolbarButtonClass(isActive({ textAlign: 'justify' }))" :disabled="!editable" title="По ширине"
              @click="run((chain) => chain.setTextAlign('justify'))">
        <AlignJustify class="size-4"/>
      </Button>

      <span class="mx-1 h-5 w-px shrink-0 bg-slate-200"/>

      <Button type="button" variant="ghost" size="icon-sm" class="size-7 shrink-0" :class="toolbarButtonClass(false)"
              :disabled="!editable" title="Очистить форматирование"
              @click="run((chain) => chain.unsetAllMarks().clearNodes())">
        <RemoveFormatting class="size-4"/>
      </Button>
    </div>

    <ContextMenu>
      <ContextMenuTrigger as-child>
        <EditorContent
            :editor="editor"
            class="tiptap-content px-4 py-3 text-sm leading-6 text-slate-900 [&_.ProseMirror]:outline-none [&_.ProseMirror]:min-h-32"
            :class="minHeight"
        />
      </ContextMenuTrigger>
      <ContextMenuContent class="w-56">
        <ContextMenuItem :disabled="!editable"
                         @select="run((chain) => chain.insertTable({ rows: 3, cols: 3, withHeaderRow: true }))">
          Вставить таблицу 3×3
        </ContextMenuItem>
        <template v-if="isActive('table')">
          <ContextMenuSeparator/>
          <ContextMenuItem :disabled="!editable" @select="run((chain) => chain.addColumnBefore())">
            Добавить столбец слева
          </ContextMenuItem>
          <ContextMenuItem :disabled="!editable" @select="run((chain) => chain.addColumnAfter())">
            Добавить столбец справа
          </ContextMenuItem>
          <ContextMenuItem :disabled="!editable" class="text-red-600 focus:bg-red-50 focus:text-red-600"
                           @select="run((chain) => chain.deleteColumn())">
            Удалить столбец
          </ContextMenuItem>
          <ContextMenuSeparator/>
          <ContextMenuItem :disabled="!editable" @select="run((chain) => chain.addRowBefore())">
            Добавить строку выше
          </ContextMenuItem>
          <ContextMenuItem :disabled="!editable" @select="run((chain) => chain.addRowAfter())">
            Добавить строку ниже
          </ContextMenuItem>
          <ContextMenuItem :disabled="!editable" class="text-red-600 focus:bg-red-50 focus:text-red-600"
                           @select="run((chain) => chain.deleteRow())">
            Удалить строку
          </ContextMenuItem>
          <ContextMenuSeparator/>
          <ContextMenuItem :disabled="!editable" @select="run((chain) => chain.toggleHeaderRow())">
            Заголовок строки
          </ContextMenuItem>
          <ContextMenuItem :disabled="!editable" @select="run((chain) => chain.toggleHeaderColumn())">
            Заголовок столбца
          </ContextMenuItem>
          <ContextMenuItem :disabled="!editable" @select="run((chain) => chain.mergeOrSplit())">
            Объединить/разделить ячейки
          </ContextMenuItem>
          <ContextMenuSeparator/>
          <ContextMenuItem :disabled="!editable" class="text-red-600 focus:bg-red-50 focus:text-red-600"
                           @select="run((chain) => chain.deleteTable())">
            Удалить таблицу
          </ContextMenuItem>
        </template>
      </ContextMenuContent>
    </ContextMenu>
  </div>
</template>

<style>
.tiptap-content .ProseMirror table {
  border-collapse: collapse;
  margin: 0.75rem 0;
  table-layout: fixed;
  width: 100%;
  overflow: hidden;
}

.tiptap-content .ProseMirror table td,
.tiptap-content .ProseMirror table th {
  border: 1px solid #cbd5e1;
  box-sizing: border-box;
  min-width: 1.5em;
  padding: 6px 8px;
  position: relative;
  vertical-align: top;
}

.tiptap-content .ProseMirror table th {
  background-color: #f1f5f9;
  font-weight: 600;
  text-align: left;
}

.tiptap-content .ProseMirror table .selectedCell {
  background-color: rgba(59, 130, 246, 0.12);
}

.tiptap-content .ProseMirror table .column-resize-handle {
  background-color: #3b82f6;
  bottom: -2px;
  pointer-events: none;
  position: absolute;
  right: -2px;
  top: 0;
  width: 3px;
}

.tiptap-content .ProseMirror.resize-cursor {
  cursor: col-resize;
}

.tiptap-content .ProseMirror .tableWrapper {
  overflow-x: auto;
  margin: 0.75rem 0;
}
</style>
