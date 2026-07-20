<script setup lang="ts">
import {toRef} from 'vue'
import {BubbleMenu} from '@tiptap/vue-3/menus'
import type {Editor} from '@tiptap/vue-3'
import type {Editor as CoreEditor} from '@tiptap/core'
import {Bold, ChevronDown, Code, Highlighter, Italic, Link2, Palette, Strikethrough, Underline as UnderlineIcon} from 'lucide-vue-next'
import {useTiptapFormatting} from '@/modules/scenario/composables/useTiptapFormatting'

const props = defineProps<{
  editor: Editor | undefined
  editable: boolean
  pluginKey: string
}>()

const {
  isActive,
  run,
  bubbleButtonClass,
  currentBlockType,
  setBlockType,
  currentColor,
  setColor,
  currentHighlightColor,
  setHighlightColor,
  setLink,
} = useTiptapFormatting(toRef(props, 'editor'), toRef(props, 'editable'))

function shouldShow({editor: bubbleEditor, from, to}: { editor: CoreEditor, from: number, to: number }) {
  return props.editable && bubbleEditor.isEditable && from !== to && bubbleEditor.state.doc.textBetween(from, to).trim().length > 0
}

const bubbleMenuOptions = {
  placement: 'top' as const,
  offset: 10,
  flip: true,
  shift: {padding: 8},
}
</script>

<template>
  <BubbleMenu
      v-if="editor"
      :editor="editor"
      :options="bubbleMenuOptions"
      :should-show="shouldShow"
      :update-delay="80"
      :plugin-key="pluginKey"
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

      <!-- Точка расширения: например, размер текста в BlockEditorGutenbergEditor -->
      <slot name="extra"/>

      <span class="mx-1 h-6 w-px bg-slate-200"/>
      <button type="button" class="inline-flex size-8 items-center justify-center rounded-md transition"
              :class="bubbleButtonClass(isActive('bold'))" @click="run((chain) => chain.toggleBold().run())">
        <Bold class="size-4"/>
      </button>
      <button type="button" class="inline-flex size-8 items-center justify-center rounded-md transition"
              :class="bubbleButtonClass(isActive('italic'))" @click="run((chain) => chain.toggleItalic().run())">
        <Italic class="size-4"/>
      </button>
      <button type="button" class="inline-flex size-8 items-center justify-center rounded-md transition"
              :class="bubbleButtonClass(isActive('underline'))" @click="run((chain) => chain.toggleUnderline().run())">
        <UnderlineIcon class="size-4"/>
      </button>
      <button type="button" class="inline-flex size-8 items-center justify-center rounded-md transition"
              :class="bubbleButtonClass(isActive('strike'))" @click="run((chain) => chain.toggleStrike().run())">
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
              :class="bubbleButtonClass(isActive('code'))" @click="run((chain) => chain.toggleCode().run())">
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
</template>
