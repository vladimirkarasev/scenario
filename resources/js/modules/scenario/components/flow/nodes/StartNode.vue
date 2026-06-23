<script setup>
import { Handle, Position } from '@vue-flow/core'

defineProps({
    data:     { type: Object,  default: () => ({}) },
    selected: { type: Boolean, default: false },
})

const cfg = {
    label: 'Начало',
    frame: 'rounded-2xl border-[3px] border-cyan-400 bg-slate-800 text-white shadow-[0_12px_32px_rgba(34,211,238,0.18)]',
    size:  'min-w-[240px] min-h-[68px] px-8 py-5',
    title: 'text-[17px] font-semibold tracking-[0.01em]',
}
</script>

<template>
    <div class="scenario-flow-node relative">
<div class="relative flex items-center justify-center text-center transition" :class="[cfg.frame, cfg.size]">
            <div :class="cfg.title">{{ data.title || cfg.label }}</div>
        </div>

        <div class="scenario-flow-connector scenario-flow-connector--rounded" :class="{ 'scenario-flow-connector--selected': selected }" />

        <Handle id="out" type="source" :position="Position.Bottom" class="scenario-flow-handle !h-4 !w-full" connectable-start :connectable-end="false" />
        <Handle id="right" type="source" :position="Position.Right" class="scenario-flow-handle !h-full !w-4" connectable-start :connectable-end="false" />
</div>
</template>

<style>@import './connector.css';</style>
