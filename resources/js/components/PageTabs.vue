<script setup lang="ts" generic="T extends string">
import type {Component} from 'vue'

interface TabItem<TId extends string> {
  id: TId
  label: string
  icon?: Component
  count?: number
}

defineProps<{
  modelValue: T
  tabs: TabItem<T>[]
}>()

defineEmits<{ 'update:modelValue': [v: T] }>()
</script>

<template>
  <nav
      class="inline-flex items-center gap-0.5 rounded-xl border border-slate-200 bg-white p-1 shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
    <button
        v-for="tab in tabs"
        :key="tab.id"
        type="button"
        class="flex h-8 items-center gap-2 rounded-lg px-3 text-[13px] font-medium transition-colors"
        :class="modelValue === tab.id
                ? 'bg-blue-600 text-white shadow-sm'
                : 'text-slate-500 hover:text-slate-800'"
        @click="$emit('update:modelValue', tab.id)"
    >
      <component :is="tab.icon" v-if="tab.icon" :size="13"/>
      {{ tab.label }}
      <span
          v-if="tab.count !== undefined"
          class="rounded-full px-1.5 py-0.5 text-[10px] font-bold tabular-nums"
          :class="modelValue === tab.id ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500'"
      >{{ tab.count }}</span>
    </button>
  </nav>
</template>
