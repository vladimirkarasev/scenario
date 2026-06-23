<script setup>
import { computed } from 'vue'
import { Handle, Position } from '@vue-flow/core'

const props = defineProps({
    data:     { type: Object,  default: () => ({}) },
    selected: { type: Boolean, default: false },
})

const cfg = {
    label:    'Условие',
    frame:    'border-[3px] border-sky-500 bg-slate-800 text-white shadow-[0_10px_30px_rgba(14,165,233,0.16)]',
    size:     'size-[152px]',
    title:    'text-[15px] font-semibold',
    subtitle: 'text-sky-100/80',
}

const title    = computed(() => props.data.title || cfg.label)
</script>

<template>
    <div class="scenario-flow-node relative">
<!-- Input handles: wider hit areas at the top and left diamond corners -->
        <!-- У condition все 4 грани работают как "ветки наружу" (юзер тянет ОТ condition).   -->
        <!-- Поэтому все handles type="source". Входящая линия от предыдущего блока тоже       -->
        <!-- подключается сюда — Vue Flow в Loose mode разрешает source→source.                -->
        <Handle id="in" type="source" :position="Position.Top" class="scenario-flow-handle !h-[15%] !w-full" connectable-start connectable-end />
        <Handle id="left" type="source" :position="Position.Left" class="scenario-flow-handle !h-full !w-[15%]" connectable-start connectable-end />

        <div class="relative flex items-center justify-center">
            <div
                class="flex items-center justify-center text-center transition"
                :class="[cfg.frame, cfg.size]"
                style="clip-path: polygon(50% 0%, 100% 50%, 50% 100%, 0% 50%)"
            >
                <div class="w-[74%] space-y-1">
                    <div :class="cfg.title">{{ title }}</div>
                    <div v-if="data.text" :class="['text-[10px] leading-4', cfg.subtitle]">{{ data.text }}</div>
                </div>
            </div>
        </div>

        <svg
            class="scenario-flow-connector scenario-flow-connector--diamond"
            :class="{ 'scenario-flow-connector--selected': selected }"
            viewBox="0 0 100 100" aria-hidden="true"
        >
            <polygon class="scenario-flow-connector-polygon" points="50 2, 98 50, 50 98, 2 50" vector-effect="non-scaling-stroke" />
        </svg>

        <Handle id="right" type="source" :position="Position.Right" class="scenario-flow-handle !h-full !w-[15%]" connectable-start connectable-end />
        <Handle id="out" type="source" :position="Position.Bottom" class="scenario-flow-handle !h-[15%] !w-full" connectable-start connectable-end />
</div>
</template>

<style>@import './connector.css';</style>
