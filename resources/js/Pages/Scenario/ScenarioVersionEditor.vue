<script setup lang="ts">
import AppShell from '@/layouts/AppShell.vue'
import ScenarioVersionTabs from '@/modules/scenario/components/ScenarioVersionTabs.vue'
import {useDashboardNavigation} from '@/composables/useDashboardNavigation'
import type {ScenarioFlowDocument} from '@/modules/scenario/lib/scenario-flow-document'
import type {ScenarioFlowEditorExpose} from '@/modules/scenario/types/scenario-flow-editor'
import {scenarioRepository} from '@/modules/scenario/repositories/scenarioRepository'
import {scenarioVersionRepository} from '@/modules/scenario/repositories/scenarioVersionRepository'
import {Button} from '@/components/ui/button'
import {Head, router} from '@inertiajs/vue3'
import {usePlayScenario} from '@/modules/scenario/composables/usePlayScenario'
import {AlertTriangle, Copy, Loader2, Play, Save} from 'lucide-vue-next'
import {computed, defineAsyncComponent, onBeforeUnmount, onMounted, ref} from 'vue'
import {toast} from 'vue-sonner'

const props = defineProps<{scenarioId: string; versionId: string}>()
const ScenarioFlowEditor = defineAsyncComponent(
    () => import('@/modules/scenario/components/flow/ScenarioFlowEditor.vue'),
)
const {navigationItems} = useDashboardNavigation()
const {launching: playLaunching, launch: launchVersion} = usePlayScenario()

const editorRef = ref<ScenarioFlowEditorExpose | null>(null)
const loading = ref(true)
const loadingError = ref<string | null>(null)
const saving = ref(false)
const duplicating = ref(false)
const dirty = ref(false)
const scenarioName = ref('')
const versionName = ref('')
const versionStatus = ref<'active' | 'draft' | 'archived'>('draft')
const versionDocument = ref<ScenarioFlowDocument>({
  format: 'scenario-flow',
  version: 1,
  viewport: {x: 0, y: 0, zoom: 1},
  blocks: [],
  connections: [],
})
const noScenarios: {id: string; name: string}[] = []

const startBlockMissing = computed(() => !versionDocument.value.blocks.some((block) => block.type === 'start'))
const startHasNoOutgoingEdge = computed(() => {
  const start = versionDocument.value.blocks.find((block) => block.type === 'start')
  return start ? !versionDocument.value.connections.some((connection) => connection.source.blockId === start.id) : false
})

function handleBeforeUnload(event: BeforeUnloadEvent): void {
  if (!dirty.value) return
  event.preventDefault()
}

const removeNavigationGuard = router.on('before', (event) => {
  if (dirty.value && !window.confirm('Есть несохранённые изменения. Покинуть страницу?')) {
    event.preventDefault()
  }
})

onMounted(async () => {
  window.addEventListener('beforeunload', handleBeforeUnload)
  try {
    const [scenario, version] = await Promise.all([
      scenarioRepository.find(props.scenarioId),
      scenarioVersionRepository.editor(props.scenarioId, props.versionId),
    ])
    scenarioName.value = scenario.name
    versionName.value = version.name ?? ''
    versionStatus.value = version.status
    const document = version.schema_json
    if (document && typeof document === 'object' && ('blocks' in document || 'nodes' in document)) {
      versionDocument.value = document as unknown as ScenarioFlowDocument
    }
  } catch (error: unknown) {
    loadingError.value = error instanceof Error ? error.message : 'Не удалось загрузить редактор версии.'
  } finally {
    loading.value = false
  }
})

onBeforeUnmount(() => {
  removeNavigationGuard()
  window.removeEventListener('beforeunload', handleBeforeUnload)
})

async function save(): Promise<void> {
  const document = editorRef.value?.getDocument() ?? versionDocument.value
  saving.value = true
  try {
    const updated = await scenarioVersionRepository.update(props.scenarioId, props.versionId, {
      name: versionName.value || null,
      status: versionStatus.value,
      schema_json: document as unknown as Record<string, unknown>,
    })
    versionDocument.value = document
    versionName.value = updated.name ?? ''
    versionStatus.value = updated.status
    editorRef.value?.markSaved()
    dirty.value = false
    toast.success('Версия сохранена')
  } catch (error: unknown) {
    toast.error(error instanceof Error ? error.message : 'Ошибка сохранения версии')
  } finally {
    saving.value = false
  }
}

async function duplicate(): Promise<void> {
  if (!window.confirm('Дублировать сохранённую версию? Несохранённые изменения не попадут в копию.')) return
  duplicating.value = true
  try {
    const copy = await scenarioVersionRepository.duplicate(props.scenarioId, props.versionId)
    toast.success('Версия дублирована')
    dirty.value = false
    router.visit(route('scenario-versions.edit', copy.id))
  } catch (error: unknown) {
    toast.error(error instanceof Error ? error.message : 'Ошибка дублирования')
  } finally {
    duplicating.value = false
  }
}
</script>

<template>
  <Head :title="versionName || 'Версия сценария'" />
  <AppShell
      :title="versionName || scenarioName || 'Версия сценария'"
      description="Редактирование версии сценария"
      :navigation-items="navigationItems"
      flush
  >
    <div v-if="loading" class="flex h-full w-full items-center justify-center text-muted-foreground">
      <Loader2 class="mr-3 size-6 animate-spin" /> Загрузка…
    </div>
    <div v-else-if="loadingError" class="flex h-full w-full items-center justify-center p-6 text-sm text-destructive">
      {{ loadingError }}
    </div>
    <div v-else class="flex h-full min-h-0 w-full flex-col">
      <div
          v-if="startBlockMissing || startHasNoOutgoingEdge"
          class="flex shrink-0 items-center gap-2 border-b border-amber-300/60 bg-amber-50 px-4 py-2 text-sm text-amber-800"
      >
        <AlertTriangle class="size-4 shrink-0" />
        <span v-if="startBlockMissing">В графе нет стартового блока «Начало».</span>
        <span v-else>Стартовый блок не соединён — прогон завершится сразу после запуска.</span>
      </div>

      <ScenarioVersionTabs active="editor" :scenario-id="scenarioId" :version-id="versionId">
        <div class="flex-1" />
        <span v-if="dirty" class="mr-2 text-xs font-medium text-amber-600">Есть несохранённые изменения</span>
        <Button variant="outline" class="h-9 gap-2 rounded-xl" :disabled="duplicating" @click="duplicate">
          <Copy class="size-4" /> {{ duplicating ? '…' : 'Дублировать' }}
        </Button>
        <Button
            variant="outline"
            class="h-9 gap-2 rounded-xl"
            :disabled="playLaunching"
            @click="launchVersion({versionId})"
        >
          <Play class="size-4" /> {{ playLaunching ? '...' : 'Запустить' }}
        </Button>
        <Button class="mr-5 h-9 gap-2 rounded-xl shadow-sm" :disabled="saving" @click="save">
          <Save class="size-4" /> {{ saving ? 'Сохраняем…' : 'Сохранить' }}
        </Button>
      </ScenarioVersionTabs>

      <div class="flex min-h-0 flex-1">
        <ScenarioFlowEditor
            ref="editorRef"
            :model-value="versionDocument"
            :scenarios="noScenarios"
            editable
            :scenario-id="scenarioId"
            :version-id="versionId"
            class="min-h-0 flex-1"
            @dirty-change="dirty = $event"
        />
      </div>
    </div>
  </AppShell>
</template>
