<script setup lang="ts">
import {computed, markRaw, nextTick, ref, watch} from 'vue'
import {
  AlignJustify, Eye, Layers,
} from 'lucide-vue-next'
import AppEditorDrawer from '@/components/AppEditorDrawer.vue'
import {Input} from '@/components/ui/input'
import {Label} from '@/components/ui/label'
import {Skeleton} from '@/components/ui/skeleton'
import BlockEditorGutenbergEditor from '@/modules/scenario/components/block-editor/BlockEditorGutenbergEditor.vue'
import BlockEditorPreviewTab from '@/modules/scenario/components/block-editor/BlockEditorPreviewTab.vue'
import BlockEditorDeleteFieldDialog from '@/modules/scenario/components/block-editor/BlockEditorDeleteFieldDialog.vue'
import BlockEditorFieldSettingsDialog
  from '@/modules/scenario/components/block-editor/BlockEditorFieldSettingsDialog.vue'
import ScenarioVariableList from '@/modules/scenario/components/ScenarioVariableList.vue'
import InputFieldSettings, {
  fieldMeta as inputMeta
} from '@/modules/scenario/components/block-editor/field-settings/InputFieldSettings.vue'
import EmailFieldSettings, {
  fieldMeta as emailMeta
} from '@/modules/scenario/components/block-editor/field-settings/EmailFieldSettings.vue'
import PhoneFieldSettings, {
  fieldMeta as phoneMeta
} from '@/modules/scenario/components/block-editor/field-settings/PhoneFieldSettings.vue'
import TextareaFieldSettings, {
  fieldMeta as textareaMeta
} from '@/modules/scenario/components/block-editor/field-settings/TextareaFieldSettings.vue'
import NumberFieldSettings, {
  fieldMeta as numberMeta
} from '@/modules/scenario/components/block-editor/field-settings/NumberFieldSettings.vue'
import HiddenFieldSettings, {
  fieldMeta as hiddenMeta
} from '@/modules/scenario/components/block-editor/field-settings/HiddenFieldSettings.vue'
import SelectFieldSettings, {
  fieldMeta as selectMeta
} from '@/modules/scenario/components/block-editor/field-settings/SelectFieldSettings.vue'
import DateFieldSettings, {
  fieldMeta as dateMeta
} from '@/modules/scenario/components/block-editor/field-settings/DateFieldSettings.vue'
import DatetimeFieldSettings, {
  fieldMeta as datetimeMeta
} from '@/modules/scenario/components/block-editor/field-settings/DatetimeFieldSettings.vue'
import DirectoryListFieldSettings, {
  fieldMeta as directoryListMeta
} from '@/modules/scenario/components/block-editor/field-settings/DirectoryListFieldSettings.vue'
import DirectoryTableFieldSettings, {
  fieldMeta as directoryTableMeta
} from '@/modules/scenario/components/block-editor/field-settings/DirectoryTableFieldSettings.vue'
import SuggestFieldSettings, {
  fieldMeta as suggestMeta
} from '@/modules/scenario/components/block-editor/field-settings/SuggestFieldSettings.vue'
import {useScenarioBlockEditorStore} from '@/modules/scenario/stores/scenarioBlockEditor'
import {storeToRefs} from 'pinia'
import {useScenarioVariables} from '@/modules/scenario/composables/useScenarioVariables'
import type {BlockField, BlockFieldType} from '@/modules/scenario/lib/scenario-block-fields'
import type {ScenarioBlock} from '@/modules/scenario/lib/scenario-flow-document'
import type {Component} from 'vue'

interface FieldPaletteItem {
  type: BlockFieldType
  label: string
  icon: Component
}

// ── Props / emits ─────────────────────────────────────────────────────────────

const props = defineProps<{
  open: boolean
  scenarioId: string
  versionId?: string | null
  blockId: string
}>()

const emit = defineEmits<{
  'update:open': [boolean]
  'update:block': [ScenarioBlock]
}>()

// ── Store ─────────────────────────────────────────────────────────────────────

const blockEditorStore = useScenarioBlockEditorStore()
const {loading, loadError, canManageCatalog, versionDocument, blockDraft} = storeToRefs(blockEditorStore)

// ── Field palette ─────────────────────────────────────────────────────────────

const fieldGroups: Array<{ title: string; items: FieldPaletteItem[] }> = [
  {
    title: 'Поля',
    items: [inputMeta, emailMeta, phoneMeta, textareaMeta, numberMeta, selectMeta, dateMeta, datetimeMeta, hiddenMeta] as FieldPaletteItem[]
  },
  {title: 'Удалённые справочники', items: [directoryListMeta, directoryTableMeta] as FieldPaletteItem[]},
  {title: 'Подсказки', items: [suggestMeta] as FieldPaletteItem[]},
]

const fieldSettingsComponents: Record<string, Component> = {
  input: markRaw(InputFieldSettings),
  email: markRaw(EmailFieldSettings),
  phone: markRaw(PhoneFieldSettings),
  textarea: markRaw(TextareaFieldSettings),
  number: markRaw(NumberFieldSettings),
  hidden: markRaw(HiddenFieldSettings),
  select: markRaw(SelectFieldSettings),
  date: markRaw(DateFieldSettings),
  datetime: markRaw(DatetimeFieldSettings),
  directory_list: markRaw(DirectoryListFieldSettings),
  directory_table: markRaw(DirectoryTableFieldSettings),
  suggest: markRaw(SuggestFieldSettings),
}

const fieldTypeMap: Partial<Record<BlockFieldType, { type: BlockFieldType; label: string; icon: Component }>> = {}
fieldGroups.forEach((g) => g.items.forEach((item) => {
  fieldTypeMap[item.type] = item
}))

function fieldTypeLabel(type: BlockFieldType): string {
  return fieldTypeMap[type]?.label ?? type
}

function fieldTypeIcon(type: BlockFieldType): Component {
  return fieldTypeMap[type]?.icon ?? markRaw(AlignJustify)
}

// ── Variables ─────────────────────────────────────────────────────────────────

const USER_VARIABLES = [
  {id: 'user.name', name: 'user.name', label: 'Имя'},
  {id: 'user.fio', name: 'user.fio', label: 'ФИО'},
  {id: 'user.email', name: 'user.email', label: 'Email'},
  {id: 'user.phone', name: 'user.phone', label: 'Телефон'},
  {id: 'user.auth_date', name: 'user.auth_date', label: 'Дата авторизации'},
]

// Единый источник переменных (как в редакторе графа): блоки графа + связанные
// сценарии. Редактируемый блок подменяем на «живой» draft, чтобы переменные
// текущего блока обновлялись на лету.
const {variables: allVariables, blocks: variableListBlocks} = useScenarioVariables(
    () => versionDocument.value.blocks.map(
        (b) => (b.id === props.blockId && blockDraft.value ? blockDraft.value : b),
    ),
    () => props.blockId,
)

// ── Copy var ──────────────────────────────────────────────────────────────────

const copiedFieldVarId = ref<string | null>(null)

async function copyFieldVarName(field: BlockField) {
  if (!field?.varName) return
  await navigator.clipboard.writeText(`{{ ${field.varName} }}`)
  copiedFieldVarId.value = field.id
  setTimeout(() => {
    copiedFieldVarId.value = null
  }, 1500)
}

// ── Field CRUD ────────────────────────────────────────────────────────────────

function addFieldAndScroll(type: BlockFieldType) {
  blockEditorStore.addField(type)
  nextTick(() => {
    const fields = blockDraft.value?.data.fields ?? []
    if (!fields.length) return
    const lastId = fields[fields.length - 1].id
    document.querySelector(`[data-field-id="${lastId}"]`)?.scrollIntoView({behavior: 'smooth', block: 'center'})
  })
}

function updateFieldSettings(field: BlockField, patch: Partial<BlockField>) {
  if (!field) return
  blockEditorStore.updateField(field.id, {...field, ...patch} as BlockField)
}

function addSelectOption(field: BlockField) {
  if (!field || field.type !== 'select') return
  updateFieldSettings(field, {
    options: [...(field.options ?? []), {id: `option_${Date.now()}`, label: '', value: '', parentId: null}],
  })
}

function reorderSelectOptions(field: BlockField, options: unknown[]) {
  if (!field || field.type !== 'select') return
  updateFieldSettings(field, {options} as Partial<BlockField>)
}

function updateSelectOption(field: BlockField, optionId: string, patch: Record<string, unknown>) {
  if (!field || field.type !== 'select') return
  updateFieldSettings(field, {
    options: (field.options ?? []).map((o) =>
        o.id === optionId
            ? {...o, ...patch, parentId: patch.parentId === '' ? null : patch.parentId ?? o.parentId}
            : o,
    ),
  } as Partial<BlockField>)
}

function removeSelectOption(field: BlockField, optionId: string) {
  if (!field || field.type !== 'select') return
  updateFieldSettings(field, {
    options: (field.options ?? [])
        .filter((o) => o.id !== optionId)
        .map((o) => o.parentId === optionId ? {...o, parentId: null} : o),
  } as Partial<BlockField>)
}

// ── Delete field dialog ───────────────────────────────────────────────────────

const confirmDeleteFieldId = ref<string | null>(null)
const confirmDeleteDialogOpen = computed({
  get: () => confirmDeleteFieldId.value !== null,
  set: (val) => {
    if (!val) confirmDeleteFieldId.value = null
  },
})
const confirmDeleteField = computed(() =>
    blockDraft.value?.data.fields.find((f: BlockField) => f.id === confirmDeleteFieldId.value) ?? null,
)

function askDeleteField(fieldId: string) {
  confirmDeleteFieldId.value = fieldId
}

function confirmDelete() {
  if (!confirmDeleteFieldId.value) return
  const id = confirmDeleteFieldId.value
  if (settingsFieldId.value === id) closeSettings()
  blockEditorStore.removeField(id)
  confirmDeleteFieldId.value = null
}

// ── Field settings dialog ─────────────────────────────────────────────────────

const settingsFieldId = ref<string | null>(null)
const settingsDialogOpen = computed({
  get: () => settingsFieldId.value !== null,
  set: (val) => {
    if (!val) settingsFieldId.value = null
  },
})
const settingsField = computed(() => blockDraft.value?.data.fields.find((f: BlockField) => f.id === settingsFieldId.value) ?? null)
const settingsFieldIndex = computed(() => blockDraft.value?.data.fields.findIndex((f: BlockField) => f.id === settingsFieldId.value) ?? -1)
const settingsComponent = computed(() => settingsField.value ? (fieldSettingsComponents[settingsField.value.type] ?? null) : null)
const isVarNameUnique = computed(() => {
  if (!settingsField.value) return true
  const fields = blockDraft.value?.data.fields ?? []
  return !fields.some((f: BlockField) => f.id !== settingsField.value!.id && f.varName === settingsField.value!.varName && settingsField.value!.varName !== '')
})

function openSettings(fieldId: string) {
  settingsFieldId.value = fieldId
}

function closeSettings() {
  settingsFieldId.value = null
}


function updateFieldById(fieldId: string, patch: Partial<BlockField>) {
  const field = blockDraft.value?.data.fields.find((f: BlockField) => f.id === fieldId)
  if (field) updateFieldSettings(field, patch)
}

// ── Tabs + lifecycle ──────────────────────────────────────────────────────────

const activeDrawerTab = ref<'editor' | 'preview'>('editor')
const isLoaded = ref(false)


watch(
    () => [props.open, props.scenarioId, props.versionId, props.blockId] as const,
    ([open]) => {
      if (!open) return
      isLoaded.value = false
      settingsFieldId.value = null
      activeDrawerTab.value = 'editor'
      blockEditorStore.initialize(props.scenarioId, props.versionId ?? null, props.blockId)
      blockEditorStore.load().then(() => {
        isLoaded.value = true
      })
    },
    {immediate: true},
)

const hasUnsavedChanges = ref(false)

watch(blockDraft, () => {
  if (!isLoaded.value) return
  hasUnsavedChanges.value = true
}, {deep: true})

function saveChanges() {
  if (!blockDraft.value) return
  blockEditorStore.syncBlockIntoDocument()
  emit('update:block', blockDraft.value)
  hasUnsavedChanges.value = false
}

function cancelChanges() {
  blockEditorStore.resetDraft()
  hasUnsavedChanges.value = false
}
</script>

<template>
  <Teleport to="body">
    <Transition enter-from-class="opacity-0" enter-active-class="transition-opacity duration-300"
                leave-to-class="opacity-0" leave-active-class="transition-opacity duration-200">
      <div v-if="open" class="fixed inset-0 z-[49] bg-black/40 backdrop-blur-sm"
           @mousedown="emit('update:open', false)"/>
    </Transition>
  </Teleport>

  <AppEditorDrawer
      :open="open"
      :title="blockDraft?.data.title || 'Редактор блока'"
      description="Block editor"
      width-class="h-full !w-auto !max-w-[75vw]"
      icon-bg-class="bg-blue-100"
      :dismissible="false"
      :modal="false"
      lock-outside
      :show-footer="Boolean(blockDraft) && !loading"
      :can-save="hasUnsavedChanges"
      @update:open="emit('update:open', $event)"
      @cancel="cancelChanges"
      @save="saveChanges"
  >
    <template #icon>
      <AlignJustify class="size-3.5 text-blue-600"/>
    </template>
    <template v-if="blockDraft?.data.fields.length" #title-badge>
      <span class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold tabular-nums text-slate-500">{{
          blockDraft.data.fields.length
        }}</span>
    </template>

    <!-- Loading -->
    <div v-if="loading" class="flex flex-1 items-center justify-center p-8">
      <div class="flex flex-col items-center gap-3">
        <div class="flex gap-1.5">
          <Skeleton class="h-2 w-2 rounded-full"/>
          <Skeleton class="h-2 w-2 rounded-full"/>
          <Skeleton class="h-2 w-2 rounded-full"/>
        </div>
        <span class="text-[12px] text-slate-400">Загрузка...</span>
      </div>
    </div>

    <!-- Error -->
    <div v-else-if="loadError" class="flex flex-1 items-center justify-center p-8">
      <div class="space-y-1 text-center">
        <div class="text-[13px] font-semibold text-slate-900">Ошибка загрузки</div>
        <div class="text-[12px] text-slate-500">{{ loadError }}</div>
      </div>
    </div>

    <!-- Content -->
    <div v-else-if="blockDraft" class="flex min-h-0 flex-1">
      <!-- Sidebar: field palette + variables -->
      <aside class="flex w-52 shrink-0 flex-col overflow-hidden border-r border-slate-200 bg-white">
        <div class="flex-1 overflow-y-auto">
          <div class="space-y-4 p-3">
            <div v-for="group in fieldGroups" :key="group.title" class="space-y-1.5">
              <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ group.title }}</div>
              <div class="grid grid-cols-2 gap-1">
                <button
                    v-for="fieldType in group.items"
                    :key="fieldType.type"
                    type="button"
                    class="flex items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50/80 px-2 py-2 text-left transition hover:border-blue-200 hover:bg-blue-50 disabled:pointer-events-none disabled:opacity-40"
                    :disabled="!canManageCatalog"
                    @click="addFieldAndScroll(fieldType.type)"
                >
                  <component :is="fieldType.icon" class="size-3.5 shrink-0 text-slate-400"/>
                  <span class="truncate text-[11px] font-medium text-slate-700">{{ fieldType.label }}</span>
                </button>
              </div>
            </div>
          </div>

          <div class="border-t border-slate-100 p-3 space-y-3">
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Переменные</div>
            <ScenarioVariableList
                :variables="allVariables"
                :blocks="variableListBlocks"
                :user-variables="USER_VARIABLES"
                :current-block-id="blockId"
                hover-class="hover:bg-slate-50"
            />
          </div>
        </div>
      </aside>

      <!-- Main -->
      <main class="flex flex-1 flex-col overflow-hidden">
        <!-- Tab bar -->
        <div class="shrink-0 border-b border-slate-200 bg-white px-6 py-2.5 min-w-[700px]">
          <div class="inline-flex items-center gap-1 rounded-lg bg-slate-100 p-1 text-[13px] font-medium">
            <button
                v-for="tab in [{ key: 'editor' as const, label: 'Поля', icon: Layers }, { key: 'preview' as const, label: 'Предпросмотр', icon: Eye }]"
                :key="tab.key"
                type="button"
                class="inline-flex cursor-pointer items-center gap-1.5 rounded-md px-3 py-1 transition"
                :class="activeDrawerTab === tab.key
                                    ? 'bg-white text-slate-900 shadow-sm'
                                    : 'text-slate-500 hover:text-slate-700'"
                @click="activeDrawerTab = tab.key"
            >
              <component :is="tab.icon" class="size-3.5"/>
              {{ tab.label }}
              <span
                  v-if="tab.key === 'editor' && blockDraft.data.fields.length"
                  class="rounded-full bg-slate-100 px-1.5 py-px text-[10px] font-bold tabular-nums text-slate-500"
                  :class="activeDrawerTab === tab.key ? 'bg-slate-100' : 'bg-white'"
              >{{ blockDraft.data.fields.length }}</span>
            </button>
          </div>
        </div>

        <!-- Scrollable content -->
        <div class="flex-1 overflow-y-auto bg-slate-50">
          <!-- Editor tab -->
          <div v-if="activeDrawerTab === 'editor'" class="mx-auto max-w-2xl space-y-2 px-6 py-6">

            <!-- Block settings (variable + skip) -->
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white p-4 space-y-3">
              <div class="space-y-1.5">
                <Label for="step-variable" class="text-[10px] font-bold uppercase tracking-wider text-slate-400">ID
                  ноды</Label>
                <Input
                    id="step-variable"
                    :model-value="blockDraft.data.variable"
                    class="border-slate-200"
                    placeholder="название_шага"
                    disabled
                    @update:model-value="(v: string | number) => blockEditorStore.updateBlockData({ variable: String(v).replace(/\s+/g, '_') })"
                />
              </div>

              <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm">
                <input
                    type="checkbox"
                    :checked="blockDraft.data.skipInSurvey"
                    class="mt-0.5 size-4 rounded border-slate-300"
                    :disabled="!canManageCatalog"
                    @change="(e: Event) => blockEditorStore.updateBlockData({ skipInSurvey: (e.target as HTMLInputElement).checked })"
                />
                <span class="grid gap-0.5">
                                        <span class="font-medium text-slate-800">Пропустить шаг в опросе</span>
                                        <span class="text-xs leading-5 text-slate-500">Шаг сохранится в схеме, но при прохождении сценария будет автоматически пропущен.</span>
                                    </span>
              </label>
            </div>

            <!-- Unified content: free text and fields live in one flowing tiptap
                 document — fields are inserted as nodes and their titles are
                 edited inline (Gutenberg-style), full configuration stays behind
                 the settings gear. -->
            <BlockEditorGutenbergEditor
                :model-value="blockDraft.data.layoutDocument"
                :fields="blockDraft.data.fields"
                :can-edit="canManageCatalog"
                :field-type-label="fieldTypeLabel"
                :field-type-icon="fieldTypeIcon"
                @update:model-value="blockEditorStore.updateBlockData({ layoutDocument: $event })"
                @update-field="updateFieldById"
                @open-settings="openSettings"
                @delete-field="askDeleteField"
                @reorder-fields="blockEditorStore.reorderFields"
            />
          </div>

          <!-- Preview tab -->
          <BlockEditorPreviewTab
              v-else
              :title="blockDraft.data.title"
              :fields="blockDraft.data.fields"
              :layout-document="blockDraft.data.layoutDocument"
          />
        </div>
      </main>
    </div>

  </AppEditorDrawer>

  <!-- Dialogs -->

  <BlockEditorDeleteFieldDialog
      v-model:open="confirmDeleteDialogOpen"
      :field="confirmDeleteField"
      @confirm="confirmDelete"
  />

  <BlockEditorFieldSettingsDialog
      v-model:open="settingsDialogOpen"
      :field="settingsField"
      :field-index="settingsFieldIndex"
      :field-count="blockDraft?.data.fields.length ?? 0"
      :field-type-label="settingsField ? fieldTypeLabel(settingsField.type) : ''"
      :field-type-icon="settingsField ? fieldTypeIcon(settingsField.type) : AlignJustify"
      :settings-component="settingsComponent"
      :can-edit="canManageCatalog"
      :is-var-name-unique="isVarNameUnique"
      :is-copied="copiedFieldVarId === settingsField?.id"
      :all-variables="allVariables"
      :blocks="variableListBlocks"
      :current-block-id="blockId"
      :user-variables="USER_VARIABLES"
      :block-title="blockDraft?.data.title || ''"
      :block-fields="blockDraft?.data.fields ?? []"
      @update="settingsField && updateFieldSettings(settingsField, $event)"
      @add-option="settingsField && addSelectOption(settingsField)"
      @update-option="settingsField && updateSelectOption(settingsField, $event.id, $event.changes)"
      @remove-option="settingsField && removeSelectOption(settingsField, $event)"
      @reorder-options="settingsField && reorderSelectOptions(settingsField, $event)"
      @move-to-index="settingsField && blockEditorStore.moveFieldToIndex(settingsField.id, $event)"
      @copy-var-name="settingsField && copyFieldVarName(settingsField)"
      @delete="settingsField && (askDeleteField(settingsField.id), closeSettings())"
  />
</template>
