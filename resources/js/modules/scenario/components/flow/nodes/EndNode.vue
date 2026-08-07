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
    <Handle id="in" type="source" :position="Position.Top" class="scenario-flow-handle !h-[10%] !w-full" connectable-start connectable-end />
    <Handle id="in_b" type="source" :position="Position.Bottom" class="scenario-flow-handle !h-[10%] !w-full" connectable-start connectable-end />
    <Handle id="left" type="source" :position="Position.Left" class="scenario-flow-handle !h-full !w-[10%]" connectable-start connectable-end />
    <Handle id="right_i" type="source" :position="Position.Right" class="scenario-flow-handle !h-full !w-[10%]" connectable-start connectable-end />

    <div class="relative flex size-[138px] items-center justify-center rounded-full border-[4px] border-orange-500 bg-slate-800 text-center text-white shadow-[0_14px_30px_rgba(249,115,22,0.16)] transition">
      <div class="text-[16px] font-semibold">
        {{ data.title || 'Конец' }}
      </div>
    </div>

    <div
        class="scenario-flow-connector scenario-flow-connector--circle"
        :class="{'scenario-flow-connector--selected': selected}"
    />
  </div>
</template>

<style>@import './connector.css';</style>
