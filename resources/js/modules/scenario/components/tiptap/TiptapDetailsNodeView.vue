<script setup lang="ts">
import {NodeViewContent, NodeViewWrapper} from '@tiptap/vue-3'
import type {NodeViewProps} from '@tiptap/core'

const props = defineProps<NodeViewProps>()

function toggleOpen() {
  props.updateAttributes({open: !props.node.attrs.open})
}

function onTitleInput(e: Event) {
  props.updateAttributes({title: (e.target as HTMLInputElement).value})
}
</script>

<template>
  <NodeViewWrapper as="div" class="tiptap-details-node my-2 overflow-hidden rounded-xl border border-slate-200">
    <div class="flex items-center gap-2 bg-slate-50 px-3 py-2">
      <input
          :value="node.attrs.title"
          type="text"
          class="min-w-0 flex-1 rounded-lg border border-transparent bg-white px-2.5 py-1 text-sm font-medium text-slate-800 shadow-sm ring-1 ring-slate-200 transition placeholder:text-slate-400 hover:ring-slate-300 focus:border-sky-400 focus:ring-sky-300 focus:outline-none"
          placeholder="Заголовок..."
          @input="onTitleInput"
      />
      <button
          type="button"
          class="shrink-0 cursor-pointer rounded-full px-2.5 py-0.5 text-[11px] font-medium transition"
          :class="node.attrs.open
                    ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200'
                    : 'bg-slate-200 text-slate-500 hover:bg-slate-300'"
          @click="toggleOpen"
      >
        {{ node.attrs.open ? 'открыто' : 'скрыто' }}
      </button>
    </div>
    <div class="border-t border-slate-200 px-3 py-2">
      <NodeViewContent/>
    </div>
  </NodeViewWrapper>
</template>
