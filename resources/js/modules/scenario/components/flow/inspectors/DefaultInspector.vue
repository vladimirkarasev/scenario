<script setup lang="ts">
/* eslint-disable vue/no-mutating-props -- draft передаётся как reactive ссылка из родителя */
import {Label} from '@/components/ui/label'
import NodeTitleSettings from '@/modules/scenario/components/flow/NodeTitleSettings.vue'
import type {ScenarioFlowNode} from '@/modules/scenario/types/scenario-flow-editor'

defineProps<{
  node: ScenarioFlowNode
  draft: Record<string, unknown>
  editable: boolean
}>()

const emit = defineEmits<{
  sync: []
}>()

</script>

<template>
  <div class="space-y-4">
    <div class="space-y-1.5">
      <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">ID ноды</Label>
      <p class="select-all rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-[12px] text-slate-700">
        {{ node.id }}
      </p>
    </div>

    <NodeTitleSettings
        v-model:title="draft.title"
        v-model:hide-title="draft.hideTitle"
        :editable="editable"
        @change="emit('sync')"
    />
  </div>
</template>
