<script setup lang="ts">
import AppShell from '@/layouts/AppShell.vue'
import ScenarioFlowEditor from '@/modules/scenario/components/flow/ScenarioFlowEditor.vue'
import VersionSettingsTab from './version-editor/VersionSettingsTab.vue'
import VersionHistoryTab from './version-editor/VersionHistoryTab.vue'
import VersionInputFieldDialog from './version-editor/VersionInputFieldDialog.vue'
import {useDashboardNavigation} from '@/composables/useDashboardNavigation'
import {type ScenarioFlowDocument} from '@/modules/scenario/lib/scenario-flow-document'
import {scenarioRepository} from '@/modules/scenario/repositories/scenarioRepository'
import {scenarioVersionRepository} from '@/modules/scenario/repositories/scenarioVersionRepository'
import {Badge} from '@/components/ui/badge'
import {Button} from '@/components/ui/button'
import {Head, Link, router} from '@inertiajs/vue3'
import {usePlayScenario} from '@/modules/scenario/composables/usePlayScenario'
import {ArrowLeft, Copy, Loader2, Play, Save} from 'lucide-vue-next'
import {computed, onMounted, reactive, ref} from 'vue'
import {toast} from 'vue-sonner'
import {useZodForm} from '@/composables/useZodForm'
import {scenarioVersionSchema} from '@/modules/scenario/schemas/scenarioSchema'
import type {ScenarioInputField, ScenarioInputFieldType} from '@/modules/scenario/types/scenario'

const props = defineProps<{ scenarioId: string; versionId: string }>()

const {navigationItems} = useDashboardNavigation()

// ── Types ──────────────────────────────────────────────────────────────────
type VersionStatus = 'active' | 'draft' | 'archived'

const {launching: playLaunching, launch: launchVersion} = usePlayScenario()

// ── State ──────────────────────────────────────────────────────────────────
const loading = ref(true)
const duplicating = ref(false)
const activeTab = ref<'editor' | 'settings' | 'history'>('editor')

const editorTabs = computed<{ id: 'editor' | 'settings' | 'history'; label: string; count?: number }[]>(() => [
  {id: 'editor', label: 'Редактор'},
  {id: 'settings', label: 'Настройки'},
  {id: 'history', label: 'История', count: revisions.value.length},
])
const scenarioName = ref('')
const revisions = ref<{ id: string; created_at: string | null }[]>([])
const versionCreatedAt = ref<string | null>(null)
const versionUpdatedAt = ref<string | null>(null)
const versionDocument = ref<ScenarioFlowDocument>({
  format: 'scenario-flow',
  version: 1,
  viewport: {x: 0, y: 0, zoom: 1},
  blocks: [],
  connections: []
})
const noScenarios = [] as { id: string; name: string }[]

const {formData: form, errors, formError: saveError, submitting: saving, submit, reset} =
    useZodForm(scenarioVersionSchema, {
      name: '',
      status: 'draft' as VersionStatus,
      input_fields: [] as ScenarioInputField[],
    })

// ── Config ─────────────────────────────────────────────────────────────────
const VERSION_STATUS_CONFIG: Record<VersionStatus, { label: string; dot: string; text: string; ring: string }> = {
  active: {label: 'Активная', dot: 'bg-emerald-500', text: 'text-emerald-700', ring: 'ring-emerald-200'},
  draft: {label: 'Черновик', dot: 'bg-amber-400', text: 'text-amber-700', ring: 'ring-amber-200'},
  archived: {label: 'Архив', dot: 'bg-slate-400', text: 'text-slate-500', ring: 'ring-slate-200'},
}

// ── Load ─────────────────────────────────────────────────────────────────────
onMounted(async () => {
  loading.value = true
  try {
    const [scenario, version] = await Promise.all([
      scenarioRepository.find(props.scenarioId),
      scenarioVersionRepository.find(props.scenarioId, props.versionId),
    ])
    scenarioName.value = scenario.name
    reset({
      name: version.name ?? '',
      status: (version.status as VersionStatus) ?? 'draft',
      input_fields: version.input_fields ?? [],
    })
    revisions.value = version.revisions
    versionCreatedAt.value = version.created_at
    versionUpdatedAt.value = version.updated_at

    const doc = version.schema_json
    if (doc && typeof doc === 'object' && ('blocks' in doc || 'nodes' in doc)) {
      versionDocument.value = doc as unknown as ScenarioFlowDocument
    }
  } finally {
    loading.value = false
  }
})

// ── Helpers ─────────────────────────────────────────────────────────────────
function fmtDate(iso: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('ru-RU', {day: 'numeric', month: 'short', year: 'numeric'})
}

function fmtDateTime(iso: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('ru-RU', {day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit'})
}

// ── Actions ──────────────────────────────────────────────────────────────────
async function save() {
  try {
    await submit(async (data) => {
      const updated = await scenarioVersionRepository.update(props.scenarioId, props.versionId, {
        name: data.name || null,
        status: data.status,
        schema_json: versionDocument.value as unknown as Record<string, unknown>,
        input_fields: data.input_fields,
      })
      reset({
        name: updated.name ?? '',
        status: (updated.status as VersionStatus) ?? 'draft',
        input_fields: updated.input_fields ?? [],
      })
      revisions.value = updated.revisions
    })
    toast.success('Версия сохранена')
  } catch (e: unknown) {
    toast.error(e instanceof Error ? e.message : 'Ошибка сохранения')
  }
}

// ── Input fields CRUD ───────────────────────────────────────────────────────
const INPUT_FIELD_TYPE_LABELS: Record<ScenarioInputFieldType, string> = {
  datetime: 'Дата и время',
  json: 'JSON',
  text: 'Текст',
  boolean: 'Boolean',
}

const fieldDialogOpen = ref(false)
const fieldDialogIdx = ref<number | null>(null)
const fieldDialogError = ref<string | null>(null)
const fieldDialogDraft = reactive<ScenarioInputField>({
  key: '',
  label: '',
  type: 'text',
})

function openFieldDialog(idx: number | null = null) {
  fieldDialogIdx.value = idx
  fieldDialogError.value = null
  if (idx === null) {
    fieldDialogDraft.key = ''
    fieldDialogDraft.label = ''
    fieldDialogDraft.type = 'text'
  } else {
    const f = form.input_fields[idx]
    fieldDialogDraft.key = f.key
    fieldDialogDraft.label = f.label
    fieldDialogDraft.type = f.type
  }
  fieldDialogOpen.value = true
}

function saveFieldDialog() {
  const key = fieldDialogDraft.key.trim()
  if (!key) {
    fieldDialogError.value = 'Ключ обязателен'
    return
  }
  if (!/^[a-z][a-z0-9_]*$/.test(key)) {
    fieldDialogError.value = 'Только латиница, цифры и _, первый символ — буква'
    return
  }
  if (form.input_fields.some((f, i) => f.key === key && i !== fieldDialogIdx.value)) {
    fieldDialogError.value = 'Ключ уже используется'
    return
  }
  const next: ScenarioInputField = {
    key,
    label: fieldDialogDraft.label.trim() || key,
    type: fieldDialogDraft.type,
  }
  if (fieldDialogIdx.value === null) {
    form.input_fields.push(next)
  } else {
    form.input_fields[fieldDialogIdx.value] = next
  }
  fieldDialogOpen.value = false
}

function removeInputField(idx: number) {
  form.input_fields.splice(idx, 1)
}

function moveInputField(idx: number, delta: number) {
  const target = idx + delta
  if (target < 0 || target >= form.input_fields.length) return
  const [item] = form.input_fields.splice(idx, 1)
  form.input_fields.splice(target, 0, item)
}

async function duplicate() {
  if (!window.confirm('Дублировать эту версию?')) return
  duplicating.value = true
  try {
    const copy = await scenarioVersionRepository.duplicate(props.scenarioId, props.versionId)
    toast.success('Версия дублирована')
    router.visit(route('scenario-versions.edit', copy.id))
  } catch (e: unknown) {
    toast.error(e instanceof Error ? e.message : 'Ошибка дублирования')
  } finally {
    duplicating.value = false
  }
}

</script>

<template>
  <Head :title="form.name || 'Версия сценария'"/>

  <AppShell
      :title="form.name"
      description="Редактирование версии сценария"
      :navigation-items="navigationItems"
      flush
  >
    <!-- Loading -->
    <div v-if="loading" class="flex h-full w-full flex-1 items-center justify-center text-muted-foreground">
      <Loader2 class="mr-3 size-6 animate-spin"/>
      Загрузка…
    </div>

    <div v-else class="flex h-full w-full flex-col">

      <!-- Save error -->
      <div
          v-if="saveError"
          class="shrink-0 rounded-none border-b border-destructive/30 bg-destructive/10 px-4 py-2 text-sm text-destructive"
      >
        {{ saveError }}
      </div>

      <!-- ── Tab bar ──────────────────────────────────────────────── -->
      <div class="flex h-12 shrink-0 items-center gap-1 border-b border-border/60 bg-background px-5">
        <Link
            class="text-slate-400 hover:text-slate-700 relative inline-flex h-12 items-center gap-2 px-3.5 text-sm font-medium transition"
            :href="route('scenarios.edit', props.scenarioId)">
          Сценарий
        </Link>
        <button
            v-for="tab in editorTabs"
            :key="tab.id"
            type="button"
            class="relative inline-flex h-12 items-center gap-2 px-3.5 text-sm font-medium transition"
            :class="activeTab === tab.id ? 'text-foreground' : 'text-slate-400 hover:text-slate-700'"
            @click="activeTab = tab.id"
        >
          {{ tab.label }}
          <span
              v-if="tab.count !== undefined"
              class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[11px] font-bold tabular-nums text-slate-500"
          >{{ tab.count }}</span>
          <span
              v-if="activeTab === tab.id"
              class="absolute inset-x-2.5 bottom-0 h-0.5 rounded-full bg-blue-600"
          />
        </button>
        <div class="flex-1"/>

        <Button variant="outline" class="h-9 gap-2 rounded-xl" :disabled="duplicating" @click="duplicate">
          <Copy class="size-4"/>
          {{ duplicating ? '…' : 'Дублировать' }}
        </Button>

        <Button
            variant="outline"
            class="h-9 gap-2 rounded-xl"
            :disabled="playLaunching"
            @click="launchVersion({ versionId: props.versionId })"
        >
          <Play class="size-4"/>
          {{ playLaunching ? '...' : 'Запустить' }}
        </Button>

        <Button class="h-9 gap-2 rounded-xl shadow-sm" :disabled="saving" @click="save">
          <Save class="size-4"/>
          {{ saving ? 'Сохраняем…' : 'Сохранить' }}
        </Button>
      </div>

      <!-- ── EDITOR TAB ───────────────────────────────────────────── -->
      <div v-if="activeTab === 'editor'" class="flex min-h-0 flex-1">
        <ScenarioFlowEditor
            v-model="versionDocument"
            :scenarios="noScenarios"
            editable
            :scenario-id="props.scenarioId"
            :version-id="props.versionId"
            class="min-h-0 flex-1"
        />
      </div>

      <!-- ── SETTINGS / HISTORY TABS ─────────────────────────────── -->
      <div v-else class="flex-1 overflow-auto p-6">
        <div class="mx-auto max-w-2xl space-y-4">
          <VersionSettingsTab
              v-if="activeTab === 'settings'"
              :form="form"
              :errors="errors"
              :save-error="saveError"
              :saving="saving"
              :version-created-at="versionCreatedAt"
              :version-updated-at="versionUpdatedAt"
              :status-config="VERSION_STATUS_CONFIG"
              :field-type-labels="INPUT_FIELD_TYPE_LABELS"
              :format-date="fmtDate"
              @save="save"
              @open-field="openFieldDialog"
              @move-field="(idx, delta) => moveInputField(idx, delta)"
              @remove-field="removeInputField"
          />

          <VersionHistoryTab
              v-else-if="activeTab === 'history'"
              :revisions="revisions"
              :format-date-time="fmtDateTime"
          />
        </div>
      </div>
    </div>
  </AppShell>

  <VersionInputFieldDialog
      v-model:open="fieldDialogOpen"
      :is-editing="fieldDialogIdx !== null"
      :draft="fieldDialogDraft"
      :error="fieldDialogError"
      @submit="saveFieldDialog"
  />
</template>
