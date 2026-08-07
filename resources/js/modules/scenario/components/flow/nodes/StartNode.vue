<script setup lang="ts">
import {Handle, Position} from '@vue-flow/core'
import type {ScenarioBlockData} from '@/modules/scenario/lib/scenario-flow-document'

withDefaults(defineProps<{
  data: ScenarioBlockData
  selected?: boolean
}>(), {
  selected: false,
})

</script>

<template>
  <div class="scenario-flow-node relative">
    <div
        class="relative flex min-h-[68px] min-w-[240px] items-center justify-center rounded-2xl border-[3px] border-cyan-400 bg-slate-800 px-8 py-5 text-center text-white shadow-[0_12px_32px_rgba(34,211,238,0.18)] transition"
    >
      <div class="text-[17px] font-semibold tracking-[0.01em]">
        {{ data.title || 'Начало' }}
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
        :connectable-end="false"
    />
    <Handle
        id="right"
        type="source"
        :position="Position.Right"
        class="scenario-flow-handle !h-full !w-4"
        connectable-start
        :connectable-end="false"
    />
  </div>
</template>

<style>@import './connector.css';</style>
