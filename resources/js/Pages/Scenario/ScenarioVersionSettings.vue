<script setup lang="ts">
import AppShell from '@/layouts/AppShell.vue'
import PageHeader from '@/components/PageHeader.vue'
import ScenarioVersionTabs from '@/modules/scenario/components/ScenarioVersionTabs.vue'
import {FormActions, FormBody, FormError, FormInput, FormSection} from '@/components/form'
import {useDashboardNavigation} from '@/composables/useDashboardNavigation'
import {useScenarioVersionSettings} from '@/modules/scenario/composables/useScenarioVersionSettings'
import {Head, router} from '@inertiajs/vue3'
import {Check, Loader2} from 'lucide-vue-next'

const props = defineProps<{scenarioId: string; versionId: string}>()
const {navigationItems} = useDashboardNavigation()
const {
  scenarioName, createdAt, updatedAt, loading, loadingError,
  form, errors, formError, submitting, save,
} = useScenarioVersionSettings(props.scenarioId, props.versionId)

const statuses = [
  {value: 'draft' as const, label: 'Черновик', description: 'Версия доступна для редактирования.', dot: 'bg-amber-400'},
  {value: 'active' as const, label: 'Активная', description: 'Версию можно использовать для запуска.', dot: 'bg-emerald-500'},
  {value: 'archived' as const, label: 'Архив', description: 'Версия сохранена только для истории.', dot: 'bg-slate-400'},
]

function formatDate(value: string | null): string {
  return value ? new Date(value).toLocaleString('ru-RU') : '—'
}
</script>

<template>
  <Head :title="form.name ? `Настройки · ${form.name}` : 'Настройки версии'" />
  <AppShell title="Настройки версии" :navigation-items="navigationItems">
    <ScenarioVersionTabs active="settings" :scenario-id="scenarioId" :version-id="versionId" />
    <div class="app-page">
      <div class="app-page-container max-w-4xl">
        <PageHeader
            title="Настройки версии"
            :subtitle="scenarioName ? `${scenarioName} · метаданные и состояние версии` : 'Метаданные и состояние версии'"
        />
        <div v-if="loading" class="flex justify-center py-16 text-sm text-slate-500">
          <Loader2 class="mr-2 size-5 animate-spin" /> Загрузка…
        </div>
        <div v-else-if="loadingError" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
          {{ loadingError }}
        </div>
        <form v-else class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" @submit.prevent="save">
          <FormBody>
            <FormError :message="formError" />
            <FormSection title="Основное" description="Название отображается в списке версий и при запуске сценария.">
              <FormInput
                  v-model="form.name"
                  name="version_name"
                  label="Название версии"
                  autocomplete="off"
                  required
                  :error="errors.name"
              />
              <div class="flex flex-wrap gap-5 text-xs text-slate-400">
                <span>Создана: {{ formatDate(createdAt) }}</span>
                <span>Обновлена: {{ formatDate(updatedAt) }}</span>
              </div>
            </FormSection>
            <FormSection title="Статус" description="Статус меняется только после сохранения формы.">
              <div class="grid gap-2 md:grid-cols-3">
                <button
                    v-for="status in statuses"
                    :key="status.value"
                    type="button"
                    class="flex items-start gap-3 rounded-xl border p-4 text-left transition"
                    :class="form.status === status.value ? 'border-blue-300 bg-blue-50 ring-1 ring-blue-200' : 'border-slate-200 hover:bg-slate-50'"
                    @click="form.status = status.value"
                >
                  <span class="mt-0.5 flex size-4 items-center justify-center rounded-full border" :class="form.status === status.value ? 'border-blue-600 bg-blue-600' : 'border-slate-300'">
                    <Check v-if="form.status === status.value" class="size-2.5 text-white" />
                  </span>
                  <span>
                    <span class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                      <span class="size-2 rounded-full" :class="status.dot" /> {{ status.label }}
                    </span>
                    <span class="mt-1 block text-xs text-slate-500">{{ status.description }}</span>
                  </span>
                </button>
              </div>
            </FormSection>
          </FormBody>
          <FormActions
              :submitting="submitting"
              submit-label="Сохранить настройки"
              @cancel="router.visit(route('scenario-versions.edit', versionId))"
          />
        </form>
      </div>
    </div>
  </AppShell>
</template>
