<script setup lang="ts">
import AppShell from '@/layouts/AppShell.vue'
import EmptyState from '@/components/EmptyState.vue'
import ListPagination from '@/components/ListPagination.vue'
import PageHeader from '@/components/PageHeader.vue'
import ScenarioVersionTabs from '@/modules/scenario/components/ScenarioVersionTabs.vue'
import {useDashboardNavigation} from '@/composables/useDashboardNavigation'
import {useScenarioVersionHistory} from '@/modules/scenario/composables/useScenarioVersionHistory'
import {Head} from '@inertiajs/vue3'
import {History, Loader2} from 'lucide-vue-next'

const props = defineProps<{scenarioId: string; versionId: string}>()
const {navigationItems} = useDashboardNavigation()
const {scenarioName, versionName, page, revisions, meta, loading, error} =
    useScenarioVersionHistory(props.scenarioId, props.versionId)

function formatDate(value: string | null): string {
  return value ? new Date(value).toLocaleString('ru-RU', {dateStyle: 'medium', timeStyle: 'short'}) : '—'
}
</script>

<template>
  <Head :title="versionName ? `История · ${versionName}` : 'История версии'" />
  <AppShell title="История версии" :navigation-items="navigationItems">
    <ScenarioVersionTabs
        active="history"
        :scenario-id="scenarioId"
        :version-id="versionId"
        :history-count="meta.total"
    />
    <div class="app-page">
      <div class="app-page-container max-w-5xl">
        <PageHeader
            title="История сохранений"
            :subtitle="scenarioName ? `${scenarioName}${versionName ? ` · ${versionName}` : ''}` : 'Ревизии версии сценария'"
        />

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <div>
              <div class="text-sm font-semibold text-slate-900">Ревизии</div>
              <div class="mt-0.5 text-xs text-slate-400">Каждое явное сохранение схемы создаёт новую ревизию.</div>
            </div>
            <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-semibold tabular-nums text-slate-600">
              {{ meta.total }}
            </span>
          </div>

          <div v-if="loading" class="flex justify-center py-16 text-sm text-slate-500">
            <Loader2 class="mr-2 size-5 animate-spin" /> Загрузка…
          </div>
          <div v-else-if="error" class="p-5 text-sm text-red-600">{{ error }}</div>
          <EmptyState
              v-else-if="!revisions.length"
              title="История пока пуста"
              subtitle="Первая ревизия появится после сохранения версии."
          >
            <template #icon><History class="size-5" /></template>
          </EmptyState>
          <div v-else class="divide-y divide-slate-100">
            <div v-for="revision in revisions" :key="revision.id" class="flex items-center gap-4 px-5 py-4">
              <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                <History class="size-4" />
              </div>
              <div class="min-w-0 flex-1">
                <div class="truncate text-sm font-semibold text-slate-800">Ревизия {{ revision.id }}</div>
                <div class="mt-0.5 text-xs text-slate-400">{{ formatDate(revision.created_at) }}</div>
              </div>
            </div>
          </div>

          <ListPagination
              v-model:current-page="page"
              :total-pages="meta.last_page"
              :total="meta.total"
              :per-page="meta.per_page"
          />
        </div>
      </div>
    </div>
  </AppShell>
</template>
