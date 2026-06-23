<script setup>
import { computed } from 'vue'
import { Handle, Position } from '@vue-flow/core'

const props = defineProps({
    data:     { type: Object,  default: () => ({}) },
    selected: { type: Boolean, default: false },
})

const hasTarget = computed(() => Boolean(props.data?.targetScenarioId))
</script>

<template>
    <div class="scenario-flow-node relative">
<!-- 4 handles so any arc on the circle can start or receive a connection.   -->
        <!-- Connections from in/in_b/left/right_i get reversed in the editor so     -->
        <Handle id="in" type="source" :position="Position.Top" class="scenario-flow-handle !h-[10%] !w-full" connectable-start connectable-end />
        <Handle id="in_b" type="source" :position="Position.Bottom" class="scenario-flow-handle !h-[10%] !w-full" connectable-start connectable-end />
        <Handle id="left" type="source" :position="Position.Left" class="scenario-flow-handle !h-full !w-[10%]" connectable-start connectable-end />
        <Handle id="right_i" type="source" :position="Position.Right" class="scenario-flow-handle !h-full !w-[10%]" connectable-start connectable-end />

        <div class="relative flex size-[160px] items-center justify-center rounded-full border-[4px] border-emerald-400 bg-emerald-500 text-center text-white shadow-[0_16px_36px_rgba(16,185,129,0.24)] transition">
            <div class="space-y-1 px-4">
                <template v-if="hasTarget">
                    <div class="text-[10px] font-medium uppercase tracking-wide text-emerald-100/70">
                        Переход к
                    </div>
                    <div class="text-[14px] font-semibold leading-tight text-white line-clamp-2 break-words">
                        {{ data.targetScenarioName || 'Сценарий' }}
                    </div>
                    <div class="text-[11px] font-medium leading-4 text-emerald-50">
                      ({{ data.targetVersionName || 'версия не выбрана' }})
                    </div>
                </template>
                <div v-else class="text-[12px] italic text-emerald-50/80">
                    Сценарий не выбран
                </div>
            </div>
        </div>

        <div class="scenario-flow-connector scenario-flow-connector--circle" :class="{ 'scenario-flow-connector--selected': selected }" />
</div>
</template>

<style>@import './connector.css';</style>
