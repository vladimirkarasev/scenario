<script setup lang="ts">
import {computed} from 'vue'
import {Handle, Position} from '@vue-flow/core'
import {MousePointerClick} from 'lucide-vue-next'
import BlockNodePreview from '@/modules/scenario/components/flow/nodes/BlockNodePreview.vue'
import type {ScenarioBlockData} from '@/modules/scenario/lib/scenario-flow-document'

const props = withDefaults(defineProps<{
  data: ScenarioBlockData
  selected?: boolean
}>(), {
  selected: false,
})

const hasPreview = computed(() => props.data.fields.length > 0 || Boolean(props.data.layoutDocument))
const showTitle = computed(() => props.data.hideTitle === false)
const showHeader = computed(() => showTitle.value || Boolean(props.data.variable))
</script>

<template>
  <div class="scenario-flow-node relative">
    <Handle
        id="in"
        type="source"
        :position="Position.Top"
        class="scenario-flow-handle !h-4 !w-full"
        connectable-start
        connectable-end
    />
    <Handle
        id="left"
        type="source"
        :position="Position.Left"
        class="scenario-flow-handle !h-full !w-4"
        connectable-start
        connectable-end
    />

    <div
        class="relative flex min-h-[190px] w-64 flex-col overflow-hidden rounded-2xl border-[3px] border-sky-500 bg-white text-slate-800 shadow-[0_8px_24px_rgba(14,165,233,0.12)] transition"
    >
      <div v-if="showHeader" class="border-b border-sky-100 bg-sky-50/60 px-3 py-2.5">
        <div v-if="showTitle" class="truncate text-xs font-semibold leading-tight text-slate-700">
          {{ data.title || 'Блок' }}
        </div>
        <div v-if="data.variable" :class="{'mt-0.5': showTitle}" class="truncate font-mono text-[8px] leading-none text-slate-400">
          {{ data.variable }}
        </div>
      </div>

      <div
          v-if="data.skipInSurvey"
          class="absolute right-3 top-2 z-10 rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[8px] font-medium leading-none text-amber-700 shadow-sm"
      >
        Пропущен
      </div>

      <div v-if="hasPreview" class="relative flex-1 px-3 py-2.5 pointer-events-none select-none">
        <BlockNodePreview :layout-document="data.layoutDocument" :fields="data.fields" />
      </div>

      <div v-else class="flex flex-1 flex-col items-center justify-center gap-1.5 text-center">
        <MousePointerClick :size="16" class="text-sky-300" />
        <span class="text-[10px] leading-snug text-slate-300">
          Дважды кликните,<br>чтобы наполнить блок
        </span>
      </div>
    </div>

    <div
        class="scenario-flow-connector scenario-flow-connector--rounded"
        :class="{'scenario-flow-connector--selected': selected}"
    />

    <Handle
        id="out"
        type="source"
        :position="Position.Bottom"
        class="scenario-flow-handle !h-4 !w-full"
        connectable-start
        connectable-end
    />
    <Handle
        id="right"
        type="source"
        :position="Position.Right"
        class="scenario-flow-handle !h-full !w-4"
        connectable-start
        connectable-end
    />
  </div>
</template>

<style>@import './connector.css';</style>
