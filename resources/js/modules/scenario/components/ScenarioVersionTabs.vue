<script setup lang="ts">
import {Link} from '@inertiajs/vue3'

defineProps<{
  active: 'editor' | 'settings' | 'history'
  scenarioId: string
  versionId: string
  historyCount?: number
}>()

const tabs = [
  {id: 'editor' as const, label: 'Редактор', routeName: 'scenario-versions.edit'},
  {id: 'settings' as const, label: 'Настройки', routeName: 'scenario-versions.settings'},
  {id: 'history' as const, label: 'История', routeName: 'scenario-versions.history'},
]
</script>

<template>
  <nav class="flex min-h-12 flex-wrap items-center gap-1 border-b border-slate-200 bg-white px-5" aria-label="Разделы версии">
    <Link
        :href="route('scenarios.edit', scenarioId)"
        class="relative inline-flex h-12 items-center px-3.5 text-sm font-medium text-slate-400 transition hover:text-slate-700"
    >
      Сценарий
    </Link>
    <Link
        v-for="tab in tabs"
        :key="tab.id"
        :href="route(tab.routeName, versionId)"
        class="relative inline-flex h-12 items-center gap-2 px-3.5 text-sm font-medium transition"
        :class="active === tab.id ? 'text-slate-900' : 'text-slate-400 hover:text-slate-700'"
    >
      {{ tab.label }}
      <span
          v-if="tab.id === 'history' && historyCount !== undefined"
          class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[11px] font-bold tabular-nums text-slate-500"
      >{{ historyCount }}</span>
      <span v-if="active === tab.id" class="absolute inset-x-2.5 bottom-0 h-0.5 rounded-full bg-blue-600" />
    </Link>
    <slot />
  </nav>
</template>
