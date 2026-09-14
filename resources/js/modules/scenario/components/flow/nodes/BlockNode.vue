<script setup lang="ts">
import {computed} from 'vue'
import {Handle, Position} from '@vue-flow/core'
import {LayoutPanelTop, MessageCircleQuestion, MousePointerClick} from 'lucide-vue-next'
import BlockNodePreview from '@/modules/scenario/components/flow/nodes/BlockNodePreview.vue'
import FlowNodeCard from '@/modules/scenario/components/flow/nodes/FlowNodeCard.vue'
import type {ScenarioBlockData} from '@/modules/scenario/lib/scenario-flow-document'

const props = withDefaults(defineProps<{
  id: string
  data: ScenarioBlockData
  selected?: boolean
  nodeKind?: 'block' | 'question'
}>(), {
  selected: false,
  nodeKind: 'block',
})

const hasPreview = computed(() => props.data.fields.length > 0 || Boolean(props.data.layoutDocument))
const isQuestion = computed(() => props.nodeKind === 'question')
</script>

<template>
  <div class="scenario-flow-node relative w-[280px]">
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

    <FlowNodeCard
        class="flex min-h-[190px] w-[280px] flex-col"
        :node-id="id"
        :label="isQuestion ? 'Вопрос' : 'Блок'"
        tone="sky"
        :selected="selected"
    >
      <template #icon>
        <MessageCircleQuestion v-if="isQuestion" class="size-3" />
        <LayoutPanelTop v-else class="size-3" />
      </template>

      <div
          v-if="data.skipInSurvey"
          class="absolute right-4 top-4 z-10 rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[8px] font-medium leading-none text-amber-700 shadow-sm"
      >
        Пропущен
      </div>

      <div v-if="hasPreview" class="pointer-events-none relative mt-2 flex-1 select-none overflow-hidden rounded-[16px] border border-slate-100 px-3 py-2.5">
        <BlockNodePreview :layout-document="data.layoutDocument" :fields="data.fields" />
      </div>

      <div v-else class="mt-2 flex flex-1 flex-col items-center justify-center gap-1.5 rounded-[16px] border border-dashed border-sky-200 text-center">
        <MousePointerClick :size="16" class="text-sky-300" />
        <span class="text-[10px] leading-snug text-slate-300">
          Дважды кликните,<br>чтобы наполнить {{ isQuestion ? 'вопрос' : 'блок' }}
        </span>
      </div>
    </FlowNodeCard>

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
