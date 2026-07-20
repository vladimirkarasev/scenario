<script setup>
import {computed} from 'vue'
import {Copy, Pencil, Settings, Trash2} from 'lucide-vue-next'

const props = defineProps({
  selectedNode: {type: Object, default: null},
  selectedEdge: {type: Object, default: null},
  selectedNodes: {type: Array, default: () => []},
  selectedEdgeFromCondition: {type: Boolean, default: false},
})

defineEmits(['edit-node', 'open-condition', 'copy', 'delete'])

const isBulkSelection = computed(() => props.selectedNodes.length > 1)
</script>

<template>
  <div
      v-if="selectedNode || selectedEdge || isBulkSelection"
      class="absolute top-0 z-20 flex w-full flex-wrap items-center justify-between gap-2 border-b border-border/60 bg-white/95 px-5 py-2.5 backdrop-blur-sm"
  >
    <div class="flex items-center gap-2.5">
      <div class="size-2 animate-pulse rounded-full bg-blue-500"/>
      <span class="text-sm font-medium text-slate-600">
                {{ isBulkSelection ? `Выбрано ${selectedNodes.length} узлов` : (selectedNode ? 'Узел выбран' : 'Связь выбрана') }}
            </span>
      <span v-if="selectedNode && !isBulkSelection" class="rounded-lg bg-slate-100 px-2 py-0.5 text-xs text-slate-500">
                {{ selectedNode.data?.title || selectedNode.id }}
            </span>
    </div>

    <div class="flex items-center gap-2">
      <button
          v-if="selectedEdgeFromCondition"
          type="button"
          class="inline-flex h-9 items-center gap-1.5 rounded-xl px-3 text-sm font-medium text-slate-600 ring-1 ring-border/60 transition hover:bg-slate-50"
          @click="$emit('open-condition')"
      >
        <Settings class="size-4"/>
        Условие
      </button>

      <button
          v-if="selectedNode && !isBulkSelection"
          type="button"
          class="inline-flex h-9 items-center gap-1.5 rounded-xl bg-blue-600 px-3.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"
          @click="$emit('edit-node')"
      >
        <Pencil class="size-3.5"/>
        Редактировать
      </button>

      <div class="mx-1 h-5 w-px bg-border/60"/>

      <button
          v-if="selectedNode || isBulkSelection"
          type="button"
          class="inline-flex size-9 items-center justify-center rounded-xl text-slate-400 transition hover:bg-slate-50 hover:text-slate-700"
          title="Копировать"
          @click="$emit('copy')"
      >
        <Copy class="size-4"/>
      </button>

      <button
          type="button"
          class="inline-flex size-9 items-center justify-center rounded-xl text-slate-400 transition hover:bg-red-50 hover:text-red-500"
          title="Удалить"
          @click="$emit('delete')"
      >
        <Trash2 class="size-4"/>
      </button>
    </div>
  </div>
</template>
