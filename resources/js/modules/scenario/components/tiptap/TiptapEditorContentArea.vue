<script setup lang="ts">
import {toRef} from 'vue'
import {EditorContent} from '@tiptap/vue-3'
import type {Editor} from '@tiptap/vue-3'
import {
  ContextMenu,
  ContextMenuContent,
  ContextMenuItem,
  ContextMenuSeparator,
  ContextMenuTrigger,
} from '@/components/ui/context-menu'
import {useTiptapFormatting} from '@/modules/scenario/composables/useTiptapFormatting'

const props = defineProps<{
  editor: Editor | undefined
  editable: boolean
  contentClass?: string
}>()

const {isActive, run} = useTiptapFormatting(toRef(props, 'editor'), toRef(props, 'editable'))
</script>

<template>
  <ContextMenu>
    <ContextMenuTrigger as-child>
      <EditorContent
          :editor="editor"
          class="tiptap-content px-4 py-3 text-sm leading-6 text-slate-900 [&_.ProseMirror]:outline-none [&_.ProseMirror]:min-h-32"
          :class="contentClass"
      />
    </ContextMenuTrigger>
    <ContextMenuContent class="w-56">
      <ContextMenuItem :disabled="!editable"
                       @select="run((chain) => chain.insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run())">
        Вставить таблицу 3×3
      </ContextMenuItem>
      <template v-if="isActive('table')">
        <ContextMenuSeparator/>
        <ContextMenuItem :disabled="!editable" @select="run((chain) => chain.addColumnBefore().run())">
          Добавить столбец слева
        </ContextMenuItem>
        <ContextMenuItem :disabled="!editable" @select="run((chain) => chain.addColumnAfter().run())">
          Добавить столбец справа
        </ContextMenuItem>
        <ContextMenuItem :disabled="!editable" class="text-red-600 focus:bg-red-50 focus:text-red-600"
                         @select="run((chain) => chain.deleteColumn().run())">
          Удалить столбец
        </ContextMenuItem>
        <ContextMenuSeparator/>
        <ContextMenuItem :disabled="!editable" @select="run((chain) => chain.addRowBefore().run())">
          Добавить строку выше
        </ContextMenuItem>
        <ContextMenuItem :disabled="!editable" @select="run((chain) => chain.addRowAfter().run())">
          Добавить строку ниже
        </ContextMenuItem>
        <ContextMenuItem :disabled="!editable" class="text-red-600 focus:bg-red-50 focus:text-red-600"
                         @select="run((chain) => chain.deleteRow().run())">
          Удалить строку
        </ContextMenuItem>
        <ContextMenuSeparator/>
        <ContextMenuItem :disabled="!editable" @select="run((chain) => chain.toggleHeaderRow().run())">
          Заголовок строки
        </ContextMenuItem>
        <ContextMenuItem :disabled="!editable" @select="run((chain) => chain.toggleHeaderColumn().run())">
          Заголовок столбца
        </ContextMenuItem>
        <ContextMenuItem :disabled="!editable" @select="run((chain) => chain.mergeOrSplit().run())">
          Объединить/разделить ячейки
        </ContextMenuItem>
        <ContextMenuSeparator/>
        <ContextMenuItem :disabled="!editable" class="text-red-600 focus:bg-red-50 focus:text-red-600"
                         @select="run((chain) => chain.deleteTable().run())">
          Удалить таблицу
        </ContextMenuItem>
      </template>
    </ContextMenuContent>
  </ContextMenu>
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

.tiptap-content .ProseMirror {
  position: relative;
}

.tiptap-content .ProseMirror-gapcursor {
  display: none;
  pointer-events: none;
  position: absolute;
  left: 0;
  right: 0;
  height: 0;
}

.tiptap-content .ProseMirror-gapcursor:before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 1px;
  background: linear-gradient(90deg, transparent, #bfdbfe 15%, #bfdbfe 85%, transparent);
}

.tiptap-content .ProseMirror-gapcursor:after {
  content: '+  Добавить';
  display: flex;
  align-items: center;
  gap: 4px;
  position: absolute;
  top: 0;
  left: 50%;
  transform: translate(-50%, -50%);
  padding: 4px 14px;
  white-space: nowrap;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.01em;
  color: #2563eb;
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  border-radius: 9999px;
  box-shadow: 0 1px 3px rgba(37, 99, 235, 0.12);
  animation: ProseMirror-gapcursor-pulse 1.6s ease-in-out infinite;
}

@keyframes ProseMirror-gapcursor-pulse {
  0%, 100% {
    opacity: 1;
    transform: translate(-50%, -50%) scale(1);
  }
  50% {
    opacity: 0.65;
    transform: translate(-50%, -50%) scale(0.97);
  }
}

.tiptap-content .ProseMirror-focused .ProseMirror-gapcursor {
  display: block;
}
</style>
