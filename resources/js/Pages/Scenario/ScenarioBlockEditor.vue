<script setup>
import AppShell from '@/layouts/AppShell.vue'
import TiptapTextEditor from '@/modules/scenario/components/tiptap/TiptapTextEditor.vue'
import {Button} from '@/components/ui/button'
import {Skeleton} from '@/components/ui/skeleton'
import {useDashboardNavigation} from '@/composables/useDashboardNavigation'
import {useScenarioBlockEditorStore} from '@/modules/scenario/stores/scenarioBlockEditor'
import {Head, Link} from '@inertiajs/vue3'
import {storeToRefs} from 'pinia'
import {computed, markRaw, nextTick, ref, watch} from 'vue'
import {AlignJustify, ArrowLeft, ChevronRight, GripVertical, Save, Trash2} from 'lucide-vue-next'
import {fieldMeta as inputMeta} from '@/modules/scenario/components/block-editor/field-settings/InputFieldSettings.vue'
import {fieldMeta as emailMeta} from '@/modules/scenario/components/block-editor/field-settings/EmailFieldSettings.vue'
import {fieldMeta as phoneMeta} from '@/modules/scenario/components/block-editor/field-settings/PhoneFieldSettings.vue'
import {
  fieldMeta as textareaMeta
} from '@/modules/scenario/components/block-editor/field-settings/TextareaFieldSettings.vue'
import {
  fieldMeta as richTextMeta
} from '@/modules/scenario/components/block-editor/field-settings/RichTextFieldSettings.vue'
import {
  fieldMeta as numberMeta
} from '@/modules/scenario/components/block-editor/field-settings/NumberFieldSettings.vue'
import {
  fieldMeta as selectMeta
} from '@/modules/scenario/components/block-editor/field-settings/SelectFieldSettings.vue'
import {fieldMeta as dateMeta} from '@/modules/scenario/components/block-editor/field-settings/DateFieldSettings.vue'
import {
  fieldMeta as datetimeMeta
} from '@/modules/scenario/components/block-editor/field-settings/DatetimeFieldSettings.vue'
import {
  fieldMeta as hiddenMeta
} from '@/modules/scenario/components/block-editor/field-settings/HiddenFieldSettings.vue'
import BlockEditorSidebar from './block-editor/BlockEditorSidebar.vue'
import BlockEditorInspector from './block-editor/BlockEditorInspector.vue'

const props = defineProps({
  scenarioId: {type: String, required: true},
  versionId: {type: String, default: null},
  blockId: {type: String, required: true},
})

const fieldGroups = [
  {
    title: 'Поля формы',
    items: [inputMeta, emailMeta, phoneMeta, textareaMeta, richTextMeta, numberMeta, selectMeta, dateMeta, datetimeMeta, hiddenMeta]
  },
]

const {navigationItems} = useDashboardNavigation()

const blockEditorStore = useScenarioBlockEditorStore()
const {
  loading, saving, loadError,
  canManageCatalog, versionDocument,
  blockDraft, pageTitle, pageDescription,
} = storeToRefs(blockEditorStore)

const selectedFieldId = ref(null)

const selectedField = computed(() =>
    blockDraft.value?.data.fields.find((f) => f.id === selectedFieldId.value) ?? null,
)

const selectedFieldIndex = computed(() =>
    blockDraft.value?.data.fields.findIndex((f) => f.id === selectedFieldId.value) ?? -1,
)

function selectField(fieldId) {
  selectedFieldId.value = selectedFieldId.value === fieldId ? null : fieldId
}


const fieldTypeMap = computed(() => {
  const map = {}
  fieldGroups.forEach((group) => group.items.forEach((item) => {
    map[item.type] = item
  }))
  return map
})

function fieldTypeLabel(type) {
  return fieldTypeMap.value[type]?.label ?? type
}

function fieldTypeIcon(type) {
  return fieldTypeMap.value[type]?.icon ?? markRaw(AlignJustify)
}

const allVariables = computed(() =>
    versionDocument.value.blocks
        .filter((block) => block.type === 'block')
        .flatMap((block) =>
            (block.data.fields ?? [])
                .filter((f) => f.type !== 'rich_text' && f.type !== 'collapse' && f.name)
                .map((f) => ({
                  fieldId: f.id,
                  blockId: block.id,
                  blockTitle: block.data.title || block.id,
                  name: `${block.id}.${f.name}`,
                  label: f.label || f.name,
                  isCurrent: block.id === props.blockId,
                })),
        ),
)

const copiedUserId = ref(null)
const copiedVarId = ref(null)

async function copyUserVariable(v) {
  await navigator.clipboard.writeText(`{{ ${v.name} }}`)
  copiedUserId.value = v.id
  setTimeout(() => {
    copiedUserId.value = null
  }, 1500)
}

async function copyBlockVariable(v) {
  await navigator.clipboard.writeText(`{{ ${v.name} }}`)
  copiedVarId.value = v.fieldId
  setTimeout(() => {
    copiedVarId.value = null
  }, 1500)
}

function addFieldAndSelect(type) {
  blockEditorStore.addField(type)
  nextTick(() => {
    const fields = blockDraft.value?.data.fields ?? []
    if (fields.length > 0) selectedFieldId.value = fields[fields.length - 1].id
  })
}

function removeFieldAndDeselect(fieldId) {
  if (selectedFieldId.value === fieldId) selectedFieldId.value = null
  blockEditorStore.removeField(fieldId)
}

function updateFieldSettings(field, patch) {
  if (!field) return
  blockEditorStore.updateField(field.id, {...field, ...patch})
}

function addSelectOption(field) {
  if (!field || field.type !== 'select') return
  const idx = field.options?.length ?? 0
  updateFieldSettings(field, {
    options: [
      ...(field.options ?? []),
      {id: `option_${Date.now()}`, label: `Вариант ${idx + 1}`, value: `option_${idx + 1}`, parentId: null},
    ],
  })
}

function updateSelectOption(field, optionId, patch) {
  if (!field || field.type !== 'select') return
  updateFieldSettings(field, {
    options: (field.options ?? []).map((o) =>
        o.id === optionId
            ? {...o, ...patch, parentId: patch.parentId === '' ? null : patch.parentId ?? o.parentId}
            : o,
    ),
  })
}

function removeSelectOption(field, optionId) {
  if (!field || field.type !== 'select') return
  updateFieldSettings(field, {
    options: (field.options ?? [])
        .filter((o) => o.id !== optionId)
        .map((o) => o.parentId === optionId ? {...o, parentId: null} : o),
  })
}

const draggedFieldId = ref(null)
const dragOverFieldId = ref(null)

function onFieldDragStart(fieldId) {
  draggedFieldId.value = fieldId
}

function onFieldDragEnter(fieldId) {
  if (!draggedFieldId.value || draggedFieldId.value === fieldId) return
  dragOverFieldId.value = fieldId
}

function onFieldDrop(targetFieldId) {
  if (!draggedFieldId.value || !blockDraft.value) return
  const idx = blockDraft.value.data.fields.findIndex((f) => f.id === targetFieldId)
  if (idx !== -1) blockEditorStore.moveFieldToIndex(draggedFieldId.value, idx)
  resetDragState()
}

function resetDragState() {
  draggedFieldId.value = null;
  dragOverFieldId.value = null
}

const copiedInspectorFieldId = ref(null)

async function copyFieldVarName(field) {
  if (!field?.name) return
  await navigator.clipboard.writeText(`{{ ${props.blockId}.${field.name} }}`)
  copiedInspectorFieldId.value = field.id
  setTimeout(() => {
    copiedInspectorFieldId.value = null
  }, 1500)
}

watch(
    () => [props.scenarioId, props.versionId, props.blockId],
    ([scenarioId, versionId, blockId]) => {
      blockEditorStore.initialize(scenarioId, versionId, blockId)
      blockEditorStore.load()
    },
    {immediate: true},
)
</script>

<template>
  <Head :title="pageTitle"/>

  <AppShell
      :title="pageTitle"
      :description="pageDescription"
      :navigation-items="navigationItems"
      flush
  >
    <div v-if="loading" class="flex flex-1 flex-col gap-4 p-8">
      <Skeleton class="h-11 w-full rounded-xl"/>
      <div class="flex flex-1 gap-4">
        <Skeleton class="h-full w-56 rounded-2xl"/>
        <Skeleton class="h-full flex-1 rounded-2xl"/>
        <Skeleton class="h-full w-[360px] rounded-2xl"/>
      </div>
    </div>

    <div v-else-if="loadError" class="flex flex-1 items-center justify-center p-8">
      <div class="space-y-1 text-center">
        <div class="text-sm font-semibold text-slate-900">Ошибка загрузки</div>
        <div class="text-sm text-slate-500">{{ loadError }}</div>
      </div>
    </div>

    <div v-else-if="blockDraft" class="flex w-full flex-1 flex-col overflow-hidden">
      <div class="flex h-11 shrink-0 items-center gap-2 border-b border-slate-200 bg-white px-3">
        <Button as-child variant="ghost" size="sm" class="h-8 gap-1.5 px-2 text-slate-600">
          <Link :href="route('scenario-versions.edit', props.versionId)">
            <ArrowLeft class="size-3.5"/>
            <span class="text-xs">Back</span>
          </Link>
        </Button>

        <div class="h-4 w-px bg-slate-200"/>

        <ChevronRight class="size-3 shrink-0 text-slate-400"/>
        <code class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-xs text-slate-600">{{ props.blockId }}</code>

        <div class="flex-1"/>

        <Button
            v-if="canManageCatalog"
            :disabled="saving"
            size="sm"
            class="h-8 gap-1.5 px-3"
            @click="blockEditorStore.save()"
        >
          <Save class="size-3.5"/>
          {{ saving ? 'Saving...' : 'Save' }}
        </Button>
      </div>

      <div class="flex flex-1 min-h-0">
        <BlockEditorSidebar
            :field-groups="fieldGroups"
            :can-manage-catalog="canManageCatalog"
            :version-document="versionDocument"
            :all-variables="allVariables"
            :block-id="props.blockId"
            :copied-user-id="copiedUserId"
            :copied-var-id="copiedVarId"
            @add-field="addFieldAndSelect"
            @copy-user="copyUserVariable"
            @copy-block="copyBlockVariable"
        />

        <main class="flex-1 overflow-y-auto bg-slate-50 py-8">
          <div class="mx-auto max-w-2xl space-y-2.5 px-6">
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
              <div class="px-6 pt-6 pb-4">
                <input
                    :value="blockDraft.data.title"
                    :disabled="!canManageCatalog"
                    placeholder="Step title..."
                    class="w-full bg-transparent text-2xl font-bold text-slate-900 outline-none placeholder:text-slate-300 disabled:cursor-default"
                    @input="blockEditorStore.updateDraft({ title: $event.target.value })"
                />
              </div>
              <div class="px-6 pb-6">
                <TiptapTextEditor
                    :model-value="blockDraft.data.text"
                    placeholder="Текст шага..."
                    :editable="canManageCatalog"
                    min-height="min-h-24"
                    @update:model-value="blockEditorStore.updateDraft({ text: $event })"
                />
              </div>
            </div>

            <div
                v-if="!blockDraft.data.fields.length"
                class="rounded-2xl border border-dashed border-slate-200 bg-white/60 px-6 py-10 text-center"
            >
              <div class="text-sm font-medium text-slate-500">Поля ещё не добавлены</div>
              <div class="mt-1 text-xs text-slate-400">Выбери тип поля в панели слева</div>
            </div>

            <div
                v-for="(field, index) in blockDraft.data.fields"
                :key="field.id"
                class="overflow-hidden rounded-2xl border bg-white shadow-sm transition-all cursor-pointer select-none"
                :class="[
                                selectedFieldId === field.id
                                    ? 'border-cyan-300 ring-2 ring-cyan-200 ring-offset-1'
                                    : 'border-slate-200 hover:border-slate-300',
                                dragOverFieldId === field.id ? 'ring-2 ring-cyan-300 ring-offset-2' : '',
                            ]"
                @click="selectField(field.id)"
                @dragenter.prevent="onFieldDragEnter(field.id)"
                @dragover.prevent
                @drop.prevent="onFieldDrop(field.id)"
            >
              <div class="flex items-center gap-2 px-3 py-2.5">
                <button
                    type="button"
                    draggable="true"
                    class="cursor-grab rounded-lg p-1 text-slate-300 transition hover:bg-slate-100 hover:text-slate-500 active:cursor-grabbing disabled:pointer-events-none"
                    :disabled="!canManageCatalog"
                    @click.stop
                    @dragstart="onFieldDragStart(field.id)"
                    @dragend="resetDragState"
                >
                  <GripVertical class="size-3.5"/>
                </button>

                <component :is="fieldTypeIcon(field.type)" class="size-3.5 shrink-0 text-slate-400"/>

                <div class="flex-1 min-w-0">
                                    <span class="text-sm font-medium text-slate-800">
                                        {{
                                        field.type === 'hidden' ? (field.name || `Field ${index + 1}`) : (field.label || `Field ${index + 1}`)
                                      }}
                                    </span>
                  <span v-if="field.name && field.type !== 'collapse'"
                        class="ml-2 font-mono text-[10px] text-slate-400">
                                        {{ props.blockId }}.{{ field.name }}
                                    </span>
                </div>

                <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-500">
                                    {{ fieldTypeLabel(field.type) }}
                                </span>

                <span v-if="field.required" class="shrink-0 text-[10px] font-semibold text-red-400">*</span>

                <button
                    type="button"
                    class="shrink-0 rounded-lg p-1 text-slate-300 transition hover:bg-red-50 hover:text-red-500 disabled:pointer-events-none"
                    :disabled="!canManageCatalog"
                    @click.stop="removeFieldAndDeselect(field.id)"
                >
                  <Trash2 class="size-3.5"/>
                </button>
              </div>

              <div v-if="field.type === 'rich_text'" class="border-t border-slate-100 px-3 pb-3 pt-2" @click.stop>
                <TiptapTextEditor
                    :model-value="field.value"
                    placeholder="Контент, который будет выведен в опросе..."
                    :editable="canManageCatalog"
                    min-height="min-h-20"
                    @update:model-value="blockEditorStore.updateField(field.id, { ...field, value: $event })"
                />
              </div>

              <div
                  v-if="field.type === 'collapse' && selectedFieldId === field.id"
                  class="border-t border-slate-100 px-3 pb-3 pt-2"
                  @click.stop
              >
                <div class="mb-2 text-[10px] font-semibold uppercase tracking-wide text-slate-400">Содержимое</div>
                <TiptapTextEditor
                    :model-value="field.value"
                    placeholder="Содержимое блока..."
                    :editable="canManageCatalog"
                    min-height="min-h-20"
                    @update:model-value="blockEditorStore.updateField(field.id, { ...field, value: $event })"
                />
              </div>
            </div>
          </div>
        </main>

        <BlockEditorInspector
            :selected-field="selectedField"
            :selected-field-index="selectedFieldIndex"
            :fields-count="blockDraft.data.fields.length"
            :can-manage-catalog="canManageCatalog"
            :block-id="props.blockId"
            :copied-inspector-field-id="copiedInspectorFieldId"
            :field-type-label="fieldTypeLabel"
            :field-type-icon="fieldTypeIcon"
            @move-up="selectedField && blockEditorStore.moveField(selectedField.id, 'up')"
            @move-down="selectedField && blockEditorStore.moveField(selectedField.id, 'down')"
            @update-field="updateFieldSettings"
            @remove-field="removeFieldAndDeselect"
            @copy-field-var="copyFieldVarName"
            @add-option="addSelectOption"
            @update-option="updateSelectOption"
            @remove-option="removeSelectOption"
        />

      </div>
    </div>
  </AppShell>
</template>
