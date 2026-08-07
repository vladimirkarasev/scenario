<script setup lang="ts">
import AppShell from '@/layouts/AppShell.vue'
import {useDashboardNavigation} from '@/composables/useDashboardNavigation'
import {scenarioRepository} from '@/modules/scenario/repositories/scenarioRepository'
import {scenarioVersionRepository} from '@/modules/scenario/repositories/scenarioVersionRepository'
import type {ScenarioVersion} from '@/modules/scenario/types/scenario-version'
import type {ScenarioCategory} from '@/modules/scenario/types/scenario'
import {Badge} from '@/components/ui/badge'
import {Button} from '@/components/ui/button'
import {FormError} from '@/components/form'
import {groupRepository} from '@/modules/groups/repositories/groupRepository'
import {Head, Link} from '@inertiajs/vue3'
import ScenarioVersionCreateDialog from '@/modules/scenario/components/pickers/ScenarioVersionCreateDialog.vue'
import PageTabs from '@/components/PageTabs.vue'
import ScenarioSettingsTab from './scenario-editor/ScenarioSettingsTab.vue'
import ScenarioVersionsTab from './scenario-editor/ScenarioVersionsTab.vue'
import {usePlayScenario} from '@/modules/scenario/composables/usePlayScenario'
import {ArrowLeft, GitBranch, Loader2} from 'lucide-vue-next'
import {computed, onMounted, ref} from 'vue'
import {toast} from 'vue-sonner'
import {useZodForm} from '@/composables/useZodForm'
import {scenarioSchema} from '@/modules/scenario/schemas/scenarioSchema'

const props = defineProps<{ scenarioId: string }>()

async function loadGroups(query: string): Promise<Array<Record<string, unknown>>> {
  const qs = new URLSearchParams({'page[size]': '20'})
  if (query) qs.set('filter[search]', query)
  const res = await groupRepository.list(qs)
  return res.data.map(g => ({id: g.id, name: g.name}))
}

const {navigationItems} = useDashboardNavigation()
const {launching: playLaunching, launch: launchVersion} = usePlayScenario()

type ScenarioStatus = 'active' | 'draft' | 'archived'

const loading = ref(true)
const canEdit = ref(true)
const activeTab = ref<'settings' | 'versions'>('settings')
const scenarioName = ref('')
const versions = ref<ScenarioVersion[]>([])
const categories = ref<ScenarioCategory[]>([])
const removingVersionId = ref<string | null>(null)
const createVersionOpen = ref(false)

const {formData: form, errors, formError: saveError, submitting: saving, submit, reset} =
    useZodForm(scenarioSchema, {
      name: '',
      description: '',
      alias: '',
      tags: '',
      status: 'draft' as ScenarioStatus,
      active_version_id: null as string | null,
      category_ids: [] as string[],
      group_ids: [] as string[],
    })

const selectedGroups = ref<Array<Record<string, unknown>>>([])

function mapStatus(isActive: boolean, activeVersionId: string | null): ScenarioStatus {
  if (!isActive) return 'archived'
  if (activeVersionId) return 'active'
  return 'draft'
}

async function loadAll(): Promise<void> {
  loading.value = true
  try {
    const [scenario, cats, vers] = await Promise.all([
      scenarioRepository.find(props.scenarioId),
      scenarioRepository.categories(),
      scenarioVersionRepository.list(props.scenarioId),
    ])

    scenarioName.value = scenario.name
    versions.value = vers

    reset({
      name: scenario.name,
      description: scenario.description ?? '',
      alias: scenario.alias ?? '',
      tags: (scenario.tags ?? []).join(', '),
      status: mapStatus(scenario.is_active, scenario.active_version_id),
      active_version_id: scenario.active_version_id,
      category_ids: scenario.categories.map(c => c.id),
      group_ids: (scenario.groups ?? []).map(g => g.id),
    })
    selectedGroups.value = (scenario.groups ?? []).map(g => ({id: g.id, name: g.name}))

    categories.value = cats
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadAll()
})

const activeVersion = computed(() =>
    versions.value.find(v => v.id === form.active_version_id) ?? null,
)

const availableCategories = computed((): Array<ScenarioCategory & { depth: number }> => {
  function flatten(parentId: string | null, depth: number): Array<ScenarioCategory & { depth: number }> {
    return categories.value
        .filter(c => c.parent_id === parentId)
        .sort((a, b) => a.name.localeCompare(b.name))
        .flatMap(c => [{...c, depth}, ...flatten(c.id, depth + 1)])
  }

  return flatten(null, 0)
})

function fmtDate(iso: string): string {
  return new Date(iso).toLocaleDateString('ru-RU', {day: 'numeric', month: 'short', year: 'numeric'})
}

function toggleCategory(id: string): void {
  form.category_ids = form.category_ids.includes(id)
      ? form.category_ids.filter(c => c !== id)
      : [...form.category_ids, id]
}

const STATUS_CONFIG: Record<ScenarioStatus, { label: string; dot: string; text: string; ring: string }> = {
  active: {label: 'Активный', dot: 'bg-emerald-500', text: 'text-emerald-700', ring: 'ring-emerald-200'},
  draft: {label: 'Черновик', dot: 'bg-amber-400', text: 'text-amber-700', ring: 'ring-amber-200'},
  archived: {label: 'Архив', dot: 'bg-slate-400', text: 'text-slate-500', ring: 'ring-slate-200'},
}

function parseTags(): string[] {
  return form.tags.split(/[\n,]/).map(s => s.trim()).filter(Boolean)
}

async function save(): Promise<void> {
  if (!canEdit.value) return
  try {
    await submit(async (data) => {
      const updated = await scenarioRepository.update(props.scenarioId, {
        name: data.name,
        description: data.description || null,
        is_active: data.status !== 'archived',
        alias: data.alias || null,
        tags: parseTags(),
        active_version_id: data.active_version_id,
        category_ids: data.category_ids,
        group_ids: selectedGroups.value.map(g => String(g.id)),
      })
      scenarioName.value = updated.name
    })
    toast.success('Сценарий обновлён')
  } catch (e: unknown) {
    toast.error(e instanceof Error ? e.message : 'Ошибка сохранения')
  }
}

async function removeVersion(version: ScenarioVersion): Promise<void> {
  const label = version.name?.trim() || version.id
  if (!window.confirm(`Удалить версию "${label}"?`)) return
  removingVersionId.value = version.id
  try {
    await scenarioVersionRepository.remove(props.scenarioId, version.id)
    versions.value = versions.value.filter(v => v.id !== version.id)
    if (form.active_version_id === version.id) {
      form.active_version_id = versions.value[0]?.id ?? null
    }
    toast.success('Версия удалена')
  } catch (e: unknown) {
    toast.error(e instanceof Error ? e.message : 'Ошибка удаления версии')
  } finally {
    removingVersionId.value = null
  }
}
</script>

<template>
  <Head :title="scenarioName || 'Сценарий'"/>

  <AppShell
      :title="scenarioName || 'Сценарий'"
      description="Настройки сценария и управление версиями"
      :navigation-items="navigationItems"
  >
    <div v-if="loading" class="flex items-center justify-center py-24 text-muted-foreground">
      <Loader2 class="mr-3 size-6 animate-spin"/>
      Загрузка…
    </div>

    <div v-else class="mx-auto max-w-5xl space-y-6 p-6">
      <div class="flex items-start justify-between gap-4">
        <div class="space-y-2">
          <h1 class="text-2xl font-semibold tracking-tight text-foreground">{{ scenarioName }}</h1>
          <div class="flex flex-wrap items-center gap-2">
            <Badge :variant="form.status === 'active' ? 'default' : 'secondary'" class="gap-1.5">
              <span class="size-1.5 rounded-full" :class="STATUS_CONFIG[form.status].dot"/>
              {{ STATUS_CONFIG[form.status].label }}
            </Badge>
            <Badge v-if="activeVersion" variant="outline" class="gap-1">
              <GitBranch class="size-3"/>
              {{ activeVersion.name }}
            </Badge>
          </div>
        </div>
        <Button variant="outline" size="sm" as-child>
          <Link :href="route('scenarios')">
            <ArrowLeft class="mr-2 size-4"/>
            Сценарии
          </Link>
        </Button>
      </div>

      <PageTabs
          v-model="activeTab"
          :tabs="[
                    { id: 'settings', label: 'Настройки' },
                    { id: 'versions', label: 'Версии', count: versions.length },
                ]"
      />

      <FormError :message="saveError"/>

      <ScenarioSettingsTab
          v-if="activeTab === 'settings'"
          :form="form"
          :errors="errors"
          :can-edit="canEdit"
          :saving="saving"
          :versions="versions"
          :active-version="activeVersion"
          :available-categories="availableCategories"
          :selected-groups="selectedGroups"
          :status-config="STATUS_CONFIG"
          :load-groups="loadGroups"
          @save="save"
          @toggle-category="toggleCategory"
          @update:selected-groups="selectedGroups = $event"
      />

      <ScenarioVersionsTab
          v-else-if="activeTab === 'versions'"
          :versions="versions"
          :active-version-id="form.active_version_id"
          :can-edit="canEdit"
          :play-launching="playLaunching"
          :format-date="fmtDate"
          @create="createVersionOpen = true"
          @play="(id) => launchVersion({ versionId: id })"
          @remove="removeVersion"
      />
    </div>
  </AppShell>

  <ScenarioVersionCreateDialog v-model:open="createVersionOpen" :scenario-id="props.scenarioId"/>
</template>
