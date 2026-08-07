<script setup lang="ts">
import {Link} from '@inertiajs/vue3'
import {KeyRound, PlugZap, ScrollText} from 'lucide-vue-next'

defineProps<{active: 'connections' | 'endpoints' | 'requests'}>()

const tabs = [
  {key: 'endpoints', label: 'Интеграции', icon: PlugZap, href: '/proxy/endpoints'},
  {key: 'connections', label: 'Доступы', icon: KeyRound, href: '/proxy/connections'},
  {key: 'requests', label: 'Запросы', icon: ScrollText, href: '/proxy/requests'},
] as const
</script>

<template>
  <div class="flex items-center gap-0.5 rounded-xl border border-border bg-card p-1 shadow-sm">
    <Link
        v-for="tab in tabs"
        :key="tab.key"
        :href="tab.href"
        class="flex h-8 items-center gap-2 rounded-lg px-3 text-[13px] font-medium transition-colors"
        :class="active === tab.key ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
    >
      <component :is="tab.icon" :size="13" />
      {{ tab.label }}
    </Link>
  </div>
</template>
