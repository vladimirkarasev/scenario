<script setup lang="ts">
import {computed} from 'vue'

type FlowNodeTone = 'cyan' | 'sky' | 'violet' | 'orange' | 'emerald'

const props = withDefaults(defineProps<{
  nodeId: string
  label: string
  tone: FlowNodeTone
  selected?: boolean
}>(), {
  selected: false,
})

const toneClasses: Record<FlowNodeTone, {
  card: string
  selected: string
  header: string
  icon: string
  label: string
}> = {
  cyan: {
    card: 'hover:border-cyan-300',
    selected: 'border-cyan-400 ring-cyan-100',
    header: 'from-cyan-50 to-sky-50/70',
    icon: 'bg-cyan-500',
    label: 'text-cyan-600',
  },
  sky: {
    card: 'hover:border-sky-300',
    selected: 'border-sky-400 ring-sky-100',
    header: 'from-sky-50 to-blue-50/70',
    icon: 'bg-sky-500',
    label: 'text-sky-600',
  },
  violet: {
    card: 'hover:border-violet-300',
    selected: 'border-violet-400 ring-violet-100',
    header: 'from-violet-50 to-fuchsia-50/60',
    icon: 'bg-violet-500',
    label: 'text-violet-600',
  },
  orange: {
    card: 'hover:border-orange-300',
    selected: 'border-orange-400 ring-orange-100',
    header: 'from-orange-50 to-amber-50/70',
    icon: 'bg-orange-500',
    label: 'text-orange-600',
  },
  emerald: {
    card: 'hover:border-emerald-300',
    selected: 'border-emerald-400 ring-emerald-100',
    header: 'from-emerald-50 to-teal-50/70',
    icon: 'bg-emerald-500',
    label: 'text-emerald-600',
  },
}

const classes = computed(() => toneClasses[props.tone])
</script>

<template>
  <div
      class="relative rounded-[22px] border border-slate-200 bg-white p-2 text-slate-800 shadow-[0_12px_32px_rgba(15,23,42,0.12)] transition-all"
      :class="[classes.card, selected ? `${classes.selected} ring-4` : '']"
  >
    <div class="flex items-center gap-1.5 rounded-xl bg-gradient-to-br px-2 py-1.5" :class="classes.header">
      <span class="flex size-5 shrink-0 items-center justify-center rounded-md text-white shadow-sm" :class="classes.icon">
        <slot name="icon" />
      </span>
      <div class="min-w-0 flex-1">
        <div class="truncate text-[9px] font-bold" :class="classes.label">{{ label }}</div>
        <div class="mt-0.5 truncate font-mono text-[8px] leading-none text-slate-400">#{{ nodeId }}</div>
      </div>
    </div>

    <slot />
  </div>
</template>
