<script setup>
/* eslint-disable vue/no-mutating-props -- draft передаётся как reactive ссылка из родителя */
import {computed, ref, watch} from 'vue'
import {Button} from '@/components/ui/button'
import {Label} from '@/components/ui/label'
import {Separator} from '@/components/ui/separator'
import {X} from 'lucide-vue-next'
import ScenarioPickerDialog from '@/modules/scenario/components/pickers/ScenarioPickerDialog.vue'
import DefaultInspector from './DefaultInspector.vue'
import {scenarioVersionRepository} from '@/modules/scenario/repositories/scenarioVersionRepository'

const props = defineProps({
  node: {type: Object, required: true},
  draft: {type: Object, required: true},
  editable: {type: Boolean, default: false},
  scenarioId: {type: String, default: null},
})

const emit = defineEmits(['sync'])

const DYNAMIC_VERSION_LABEL = 'последняя активная версия'

const pickerOpen = ref(false)
const versions = ref([])
const versionsLoading = ref(false)
let lastLoadedScenarioId = null

async function loadVersionsFor(scenarioId) {
  if (!scenarioId) {
    versions.value = []
    lastLoadedScenarioId = null
    return
  }
  if (lastLoadedScenarioId === scenarioId) return
  versionsLoading.value = true
  try {
    versions.value = await scenarioVersionRepository.list(scenarioId)
    lastLoadedScenarioId = scenarioId
  } catch {
    versions.value = []
  } finally {
    versionsLoading.value = false
  }
}

function onVersionChange() {
  if (props.draft.targetVersionId == null) {
    props.draft.targetVersionName = DYNAMIC_VERSION_LABEL
  } else {
    const v = versions.value.find((x) => x.id === props.draft.targetVersionId)
    props.draft.targetVersionName = v ? (v.name || null) : null
  }
  emit('sync')
}

watch(
    () => props.draft.targetScenarioId,
    (id) => {
      if (id) void loadVersionsFor(id)
      else versions.value = []
    },
    {immediate: true},
)

function onScenarioPicked(scenario) {
  props.draft.targetScenarioId = scenario.id
  props.draft.targetScenarioName = scenario.name
  props.draft.targetVersionId = null
  props.draft.targetVersionName = DYNAMIC_VERSION_LABEL
  void loadVersionsFor(scenario.id)
  emit('sync')
}

function clearLinkedScenario() {
  props.draft.targetScenarioId = null
  props.draft.targetScenarioName = null
  props.draft.targetVersionId = null
  props.draft.targetVersionName = null
  versions.value = []
  lastLoadedScenarioId = null
  emit('sync')
}

const linkedName = computed(() => props.draft.targetScenarioName || 'Сценарий')
</script>

<template>
  <div class="space-y-4">
    <DefaultInspector
        :node="node"
        :draft="draft"
        :editable="editable"
        @sync="emit('sync')"
    />

    <Separator class="bg-slate-200"/>

    <div class="space-y-2">
      <Label>Переход в сценарий</Label>
      <div
          v-if="draft.targetScenarioId"
          class="flex items-start justify-between gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5"
      >
        <div class="min-w-0">
          <div class="truncate text-sm font-semibold text-slate-900">{{ linkedName }}</div>
          <div class="mt-0.5 font-mono text-[10.5px] text-slate-400">{{ draft.targetScenarioId }}</div>
        </div>
        <div class="flex shrink-0 gap-1">
          <Button type="button" variant="outline" size="sm" :disabled="!editable" @click="pickerOpen = true">
            Изменить
          </Button>
          <Button type="button" variant="ghost" size="sm" :disabled="!editable" @click="clearLinkedScenario">
            <X class="size-3.5"/>
          </Button>
        </div>
      </div>
      <Button
          v-else
          type="button"
          variant="outline"
          class="w-full"
          :disabled="!editable"
          @click="pickerOpen = true"
      >
        Выбрать сценарий
      </Button>
    </div>

    <div v-if="draft.targetScenarioId" class="space-y-2">
      <Label for="target-version">Версия сценария</Label>
      <select
          id="target-version"
          v-model="draft.targetVersionId"
          class="flex h-10 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
          :disabled="!editable || versionsLoading"
          @change="onVersionChange"
      >
        <option :value="null">Последняя активная версия</option>
        <option v-for="v in versions" :key="v.id" :value="v.id">
          {{ v.name || v.id.slice(0, 8) }}{{ v.status === 'active' ? ' · активная' : '' }}
        </option>
      </select>
      <p class="text-[11px] text-slate-400">
        «Последняя активная версия» — переход всегда идёт на версию со статусом «активная».
      </p>
      <p v-if="!versionsLoading && !versions.length" class="text-[11px] text-amber-600">
        У сценария нет версий.
      </p>
    </div>

    <ScenarioPickerDialog
        v-model:open="pickerOpen"
        :exclude-scenario-id="scenarioId"
        @select="onScenarioPicked"
    />
  </div>
</template>
