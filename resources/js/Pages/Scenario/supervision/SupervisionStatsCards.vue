<script setup lang="ts">
import {Activity, CheckCircle2, LayoutList, XCircle} from 'lucide-vue-next'
import type {Component} from 'vue'

interface StatCard {
  value: string
  label: string
  sub: string
  icon: Component
  statKey: 'total' | 'active' | 'completed' | 'failed'
  dot: string
}

defineProps<{
  activeStatus: string
  statValues: Record<'total' | 'active' | 'completed' | 'failed', number | null>
  loading: boolean
}>()

defineEmits<{ pick: [value: string] }>()

const STATUS_CARDS: StatCard[] = [
  {value: '', label: 'Всего', sub: 'опросов', icon: LayoutList, statKey: 'total', dot: ''},
  {value: 'active', label: 'В процессе', sub: 'активных', icon: Activity, statKey: 'active', dot: 'bg-blue-400'},
  {
    value: 'completed',
    label: 'Завершено',
    sub: 'успешно',
    icon: CheckCircle2,
    statKey: 'completed',
    dot: 'bg-emerald-400'
  },
  {value: 'failed', label: 'С ошибкой', sub: 'требуют внимания', icon: XCircle, statKey: 'failed', dot: 'bg-red-400'},
]
</script>

<template>
  <div class="mb-6 grid grid-cols-4 gap-4">
    <button
        v-for="card in STATUS_CARDS"
        :key="card.value"
        type="button"
        class="rounded-xl border bg-white px-5 py-4 text-left shadow-[0_1px_2px_rgba(15,23,42,0.04)] transition hover:border-blue-200"
        :class="activeStatus === card.value
                ? 'border-blue-300 ring-1 ring-blue-200'
                : 'border-slate-200'"
        @click="$emit('pick', card.value)"
    >
      <div class="mb-2 flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
        <component :is="card.icon" :size="11"/>
        {{ card.label }}
      </div>
      <div
          class="text-3xl font-bold tabular-nums"
          :class="activeStatus === card.value ? 'text-blue-600' : 'text-slate-900'"
      >
        <span v-if="loading" class="inline-block h-8 w-12 animate-pulse rounded-lg bg-slate-100"/>
        <span v-else>{{ statValues[card.statKey] ?? '—' }}</span>
      </div>
      <div class="mt-0.5 text-[11px] text-slate-400">{{ card.sub }}</div>
    </button>
  </div>
</template>
