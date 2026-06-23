<script setup>
import {Handle, Position} from '@vue-flow/core'

defineProps({
  data: {type: Object, default: () => ({})},
  selected: {type: Boolean, default: false},
})

const cfg = {
  label: 'Конец',
  frame: 'rounded-full border-[4px] border-orange-500 bg-slate-800 text-white shadow-[0_14px_30px_rgba(249,115,22,0.16)]',
  size: 'size-[138px]',
  title: 'text-[16px] font-semibold',
}
</script>

<template>
  <div class="scenario-flow-node relative">
    <Handle id="in" type="source" :position="Position.Top" class="scenario-flow-handle !h-[10%] !w-full"
            connectable-start connectable-end/>
    <Handle id="in_b" type="source" :position="Position.Bottom" class="scenario-flow-handle !h-[10%] !w-full"
            connectable-start connectable-end/>
    <Handle id="left" type="source" :position="Position.Left" class="scenario-flow-handle !h-full !w-[10%]"
            connectable-start connectable-end/>
    <Handle id="right_i" type="source" :position="Position.Right" class="scenario-flow-handle !h-full !w-[10%]"
            connectable-start connectable-end/>

    <div class="relative flex items-center justify-center text-center transition" :class="[cfg.frame, cfg.size]">
      <div :class="cfg.title">{{ cfg.label }}</div>
    </div>

    <div class="scenario-flow-connector scenario-flow-connector--circle"
         :class="{ 'scenario-flow-connector--selected': selected }"/>
  </div>
</template>

<style>@import './connector.css';</style>
