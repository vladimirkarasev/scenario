<script setup lang="ts">
import {CircleStop, Diamond, ExternalLink, LayoutPanelTop, MessageCircleQuestion, Play, Zap} from 'lucide-vue-next'
import type {NodeType} from '@/modules/scenario/lib/scenario-flow-document'
import {useScenarioNodeCatalog} from '@/modules/scenario/composables/useScenarioNodeCatalog'

defineProps<{ editable: boolean }>()
const emit = defineEmits<{ add: [type: NodeType] }>()

const {nodes, loading, error} = useScenarioNodeCatalog()
</script>

<template>
  <aside class="flex flex-col gap-3 border-r border-border/60 bg-white p-4">
    <div class="px-1 text-[11px] font-bold uppercase tracking-wider text-slate-400">Элементы</div>
    <div v-if="loading" class="px-1 text-xs text-slate-400">Загрузка…</div>
    <div v-else-if="error" class="px-1 text-xs text-rose-500">{{ error }}</div>
    <div class="space-y-1.5">
      <button
          v-for="item in nodes"
          :key="item.type"
          type="button"
          class="flex w-full items-center gap-3 rounded-xl border border-border/60 bg-slate-50/80 px-3 py-2.5 text-left transition hover:border-blue-300 hover:bg-blue-50 hover:shadow-sm disabled:pointer-events-none disabled:opacity-40"
          :disabled="!editable"
          @click="emit('add', item.type)"
      >
                <span
                    class="flex size-6 shrink-0 items-center justify-center rounded-lg bg-white ring-1 ring-border/60">
                    <Play v-if="item.type === 'start'" class="size-3.5 fill-current text-cyan-600" />
                    <LayoutPanelTop v-else-if="item.type === 'block'" class="size-3.5 text-sky-600" />
                    <MessageCircleQuestion v-else-if="item.type === 'question'" class="size-3.5 text-cyan-600" />
                    <Zap v-else-if="item.type === 'action'" class="size-3.5 text-violet-600" />
                    <Diamond v-else-if="item.type === 'condition'" class="size-3.5 text-sky-600" />
                    <CircleStop v-else-if="item.type === 'end'" class="size-3.5 text-orange-600" />
                    <ExternalLink v-else-if="item.type === 'scenario_link'" class="size-3.5 text-emerald-600" />
                </span>
        <span class="truncate text-sm font-medium text-slate-700">{{ item.label }}</span>
      </button>
    </div>
  </aside>
</template>
