<script setup lang="ts">
import {toRef} from 'vue'
import type {Editor} from '@tiptap/vue-3'
import {Button} from '@/components/ui/button'
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
import {useTiptapFormatting} from '@/modules/scenario/composables/useTiptapFormatting'

const props = defineProps<{
  editor: Editor | undefined
  editable: boolean
}>()

const {
  isActive,
  run,
  toolbarButtonClass,
  currentBlockType,
  setBlockType,
  currentColor,
  setColor,
  currentHighlightColor,
  setHighlightColor,
  setLink,
} = useTiptapFormatting(toRef(props, 'editor'), toRef(props, 'editable'))
</script>

<template>
  <div class="flex flex-wrap items-center gap-0 border-b border-slate-200 bg-white px-1.5 py-1">
    <Button type="button" variant="ghost" size="icon-sm" class="size-7 shrink-0" :class="toolbarButtonClass(false)"
            :disabled="!editable || !editor?.can().undo()" title="Отменить" @click="run((chain) => chain.undo().run())">
      <Undo2 class="size-4"/>
    </Button>
    <Button type="button" variant="ghost" size="icon-sm" class="size-7 shrink-0" :class="toolbarButtonClass(false)"
            :disabled="!editable || !editor?.can().redo()" title="Повторить" @click="run((chain) => chain.redo().run())">
      <Redo2 class="size-4"/>
    </Button>

    <span class="mx-1 h-5 w-px shrink-0 bg-slate-200"/>

    <Button type="button" variant="ghost" size="icon-sm" class="size-7 shrink-0"
            :class="toolbarButtonClass(isActive('bold'))" :disabled="!editable" title="Жирный"
            @click="run((chain) => chain.toggleBold().run())">
      <Bold class="size-4"/>
    </Button>
    <Button type="button" variant="ghost" size="icon-sm" class="size-7 shrink-0"
            :class="toolbarButtonClass(isActive('italic'))" :disabled="!editable" title="Курсив"
            @click="run((chain) => chain.toggleItalic().run())">
      <Italic class="size-4"/>
    </Button>
    <Button type="button" variant="ghost" size="icon-sm" class="size-7 shrink-0"
            :class="toolbarButtonClass(isActive('underline'))" :disabled="!editable" title="Подчеркнуть"
            @click="run((chain) => chain.toggleUnderline().run())">
      <UnderlineIcon class="size-4"/>
    </Button>
    <Button type="button" variant="ghost" size="icon-sm" class="size-7 shrink-0"
            :class="toolbarButtonClass(isActive('strike'))" :disabled="!editable" title="Зачеркнуть"
            @click="run((chain) => chain.toggleStrike().run())">
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
            @click="run((chain) => chain.toggleCode().run())">
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
            @click="run((chain) => chain.toggleBulletList().run())">
      <List class="size-4"/>
    </Button>
    <Button type="button" variant="ghost" size="icon-sm" class="size-7 shrink-0"
            :class="toolbarButtonClass(isActive('orderedList'))" :disabled="!editable" title="Нумерованный список"
            @click="run((chain) => chain.toggleOrderedList().run())">
      <ListOrdered class="size-4"/>
    </Button>
    <Button type="button" variant="ghost" size="icon-sm" class="size-7 shrink-0"
            :class="toolbarButtonClass(isActive('details'))" :disabled="!editable" title="Раскрывающийся блок"
            @click="run((chain) => chain.setDetails().run())">
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
            :disabled="!editable" title="Убрать ссылку" @click="run((chain) => chain.unsetLink().run())">
      <Unlink class="size-4"/>
    </Button>

    <span class="mx-1 h-5 w-px shrink-0 bg-slate-200"/>

    <Button type="button" variant="ghost" size="icon-sm" class="size-7 shrink-0"
            :class="toolbarButtonClass(isActive('blockquote'))" :disabled="!editable" title="Цитата"
            @click="run((chain) => chain.toggleBlockquote().run())">
      <Quote class="size-4"/>
    </Button>
    <Button type="button" variant="ghost" size="icon-sm" class="size-7 shrink-0" :class="toolbarButtonClass(false)"
            :disabled="!editable" title="Горизонтальная линия" @click="run((chain) => chain.setHorizontalRule().run())">
      <Minus class="size-4"/>
    </Button>

    <span class="mx-1 h-5 w-px shrink-0 bg-slate-200"/>

    <Button type="button" variant="ghost" size="icon-sm" class="size-7 shrink-0"
            :class="toolbarButtonClass(isActive('table'))" :disabled="!editable"
            title="Вставить таблицу (правый клик в таблице — управление)"
            @click="run((chain) => chain.insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run())">
      <TableIcon class="size-4"/>
    </Button>

    <span class="mx-1 h-5 w-px shrink-0 bg-slate-200"/>

    <Button type="button" variant="ghost" size="icon-sm" class="size-7 shrink-0"
            :class="toolbarButtonClass(isActive({ textAlign: 'left' }))" :disabled="!editable" title="По левому краю"
            @click="run((chain) => chain.setTextAlign('left').run())">
      <AlignLeft class="size-4"/>
    </Button>
    <Button type="button" variant="ghost" size="icon-sm" class="size-7 shrink-0"
            :class="toolbarButtonClass(isActive({ textAlign: 'center' }))" :disabled="!editable" title="По центру"
            @click="run((chain) => chain.setTextAlign('center').run())">
      <AlignCenter class="size-4"/>
    </Button>
    <Button type="button" variant="ghost" size="icon-sm" class="size-7 shrink-0"
            :class="toolbarButtonClass(isActive({ textAlign: 'right' }))" :disabled="!editable"
            title="По правому краю" @click="run((chain) => chain.setTextAlign('right').run())">
      <AlignRight class="size-4"/>
    </Button>
    <Button type="button" variant="ghost" size="icon-sm" class="size-7 shrink-0"
            :class="toolbarButtonClass(isActive({ textAlign: 'justify' }))" :disabled="!editable" title="По ширине"
            @click="run((chain) => chain.setTextAlign('justify').run())">
      <AlignJustify class="size-4"/>
    </Button>

    <span class="mx-1 h-5 w-px shrink-0 bg-slate-200"/>

    <Button type="button" variant="ghost" size="icon-sm" class="size-7 shrink-0" :class="toolbarButtonClass(false)"
            :disabled="!editable" title="Очистить форматирование"
            @click="run((chain) => chain.unsetAllMarks().clearNodes().run())">
      <RemoveFormatting class="size-4"/>
    </Button>
  </div>
</template>
