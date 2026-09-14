<script setup lang="ts">
import {computed, defineAsyncComponent} from 'vue'
import {Loader2, X} from 'lucide-vue-next'
import {Button} from '@/components/ui/button'
import {
  Drawer,
  DrawerContent,
  DrawerDescription,
  DrawerHeader,
  DrawerTitle,
} from '@/components/ui/drawer'
import ConditionEdgeSettingsDialog from '@/modules/scenario/components/flow/ConditionEdgeSettingsDialog.vue'
import BlockInspector from '@/modules/scenario/components/flow/inspectors/BlockInspector.vue'
import ConditionInspector from '@/modules/scenario/components/flow/inspectors/ConditionInspector.vue'
import DefaultInspector from '@/modules/scenario/components/flow/inspectors/DefaultInspector.vue'
import EndInspector from '@/modules/scenario/components/flow/inspectors/EndInspector.vue'
import ScenarioLinkInspector from '@/modules/scenario/components/flow/inspectors/ScenarioLinkInspector.vue'
import {USER_VARIABLES, type FlowLogicalVariable} from '@/modules/scenario/lib/scenario-flow-constants'
import {isContentNodeType, type ScenarioBlock, type ScenarioBlockData} from '@/modules/scenario/lib/scenario-flow-document'
import type {LinkedBlockEntry} from '@/modules/scenario/composables/useLinkedScenarioVariables'
import type {VariableListBlock} from '@/modules/scenario/composables/useScenarioVariables'
import type {VariableEntry} from '@/modules/scenario/types/scenario-variable-entry'
import type {ScenarioFlowEdge, ScenarioFlowNode} from '@/modules/scenario/types/scenario-flow-editor'

interface ConditionPreviewOption {
  label: string
  icon: string | null
  targetNodeId: string
}

const props = defineProps<{
  drawerOpen: boolean
  blockEditorDrawerOpen: boolean
  conditionSettingsOpen: boolean
  actionEditorOpen: boolean
  selectedNode: ScenarioFlowNode | null
  selectedEdge: ScenarioFlowEdge | null
  selectedEdgeFromCondition: boolean
  conditionPreviewQuestion: string
  conditionPreviewHideTitle: boolean
  conditionPreviewContent?: unknown
  conditionPreviewOptions: ConditionPreviewOption[]
  copiedConditionVariableId: string | null
  inspectorDraft: Record<string, unknown>
  variableEntries: VariableEntry[]
  variableListBlocks: Array<VariableListBlock | LinkedBlockEntry>
  editingBlockId: string | null
  editable: boolean
  scenarioId: string | null
  versionId: string | null
}>()

const emit = defineEmits<{
  'update:drawerOpen': [value: boolean]
  'update:blockEditorDrawerOpen': [value: boolean]
  'update:conditionSettingsOpen': [value: boolean]
  'update:actionEditorOpen': [value: boolean]
  syncSelectedNode: []
  updateBlockVariable: [value: unknown]
  openBlockEditor: []
  commitInspector: []
  cancelInspector: []
  applyConditionLogicalVariable: [variable: FlowLogicalVariable]
  updateConditionEdge: [payload: {key: string; value: unknown}]
  blockUpdate: [block: ScenarioBlock]
  actionUpdate: [data: Partial<ScenarioBlockData>]
}>()

const ScenarioBlockEditorDrawer = defineAsyncComponent(
    () => import('@/modules/scenario/components/block-editor/ScenarioBlockEditorDrawer.vue'),
)
const ActionNodeEditorDrawer = defineAsyncComponent(
    () => import('@/modules/scenario/components/actions/ActionNodeEditorDrawer.vue'),
)

const drawerOpenModel = computed({
  get: () => props.drawerOpen,
  set: (value: boolean) => emit('update:drawerOpen', value),
})
const blockEditorDrawerOpenModel = computed({
  get: () => props.blockEditorDrawerOpen,
  set: (value: boolean) => emit('update:blockEditorDrawerOpen', value),
})
const conditionSettingsOpenModel = computed({
  get: () => props.conditionSettingsOpen,
  set: (value: boolean) => emit('update:conditionSettingsOpen', value),
})
const actionEditorOpenModel = computed({
  get: () => props.actionEditorOpen,
  set: (value: boolean) => emit('update:actionEditorOpen', value),
})
</script>

<template>
  <Drawer v-model:open="drawerOpenModel" direction="right" handle-only>
    <DrawerContent
        class="h-full"
        :class="selectedNode?.type === 'condition' || selectedNode?.type === 'end'
          ? '!w-[64vw] !max-w-[64vw]'
          : 'sm:max-w-md'"
        @pointer-down-outside="$event.preventDefault()"
        @focus-outside="$event.preventDefault()"
        @interact-outside="$event.preventDefault()"
    >
      <template v-if="selectedNode?.type === 'condition'">
        <DrawerTitle class="sr-only">Inspector: Condition</DrawerTitle>
        <DrawerDescription class="sr-only">Condition editor</DrawerDescription>

        <div class="flex h-10 shrink-0 items-center justify-end border-b border-slate-200 px-3">
          <Button type="button" variant="ghost" size="icon" @click="emit('cancelInspector')">
            <X class="size-4" />
          </Button>
        </div>
      </template>

      <DrawerHeader v-else class="border-b border-slate-200 text-left">
        <DrawerTitle>Инспектор</DrawerTitle>
        <DrawerDescription class="sr-only">Inspector</DrawerDescription>
      </DrawerHeader>

      <div class="min-h-0 flex-1 overflow-hidden">
        <ConditionInspector
            v-if="selectedNode?.type === 'condition'"
            :node="selectedNode"
            :draft="inspectorDraft"
            :editable="editable"
            :variables="variableEntries"
            :blocks="variableListBlocks"
            @sync="emit('syncSelectedNode')"
        />

        <EndInspector
            v-else-if="selectedNode?.type === 'end'"
            :node="selectedNode"
            :draft="inspectorDraft"
            :editable="editable"
            :variables="variableEntries"
            :blocks="variableListBlocks"
            @sync="emit('syncSelectedNode')"
        />

        <div v-else-if="selectedNode" class="space-y-4 overflow-y-auto px-4 py-4">
          <BlockInspector
              v-if="isContentNodeType(selectedNode.type)"
              :node="selectedNode"
              :draft="inspectorDraft"
              :editable="editable"
              :scenario-id="scenarioId"
              :version-id="versionId"
              @sync="emit('syncSelectedNode')"
              @update-variable="emit('updateBlockVariable', $event)"
              @open-editor="emit('openBlockEditor')"
          />

          <ScenarioLinkInspector
              v-else-if="selectedNode.type === 'scenario_link'"
              :node="selectedNode"
              :draft="inspectorDraft"
              :editable="editable"
              :scenario-id="scenarioId"
              @sync="emit('syncSelectedNode')"
          />

          <template v-else>
            <DefaultInspector
                :node="selectedNode"
                :draft="inspectorDraft"
                :editable="editable"
                @sync="emit('syncSelectedNode')"
            />

            <div v-if="selectedNode.type === 'action'" class="rounded-2xl border border-blue-100 bg-blue-50 p-3">
              <div class="flex flex-col gap-3">
                <div class="text-sm font-medium text-blue-900">Редактор шага</div>
                <Button class="w-full gap-2" @click="actionEditorOpenModel = true">
                  Редактировать
                </Button>
              </div>
            </div>
          </template>
        </div>
      </div>

      <div
          v-if="selectedNode"
          class="flex shrink-0 items-center justify-end gap-2 border-t border-slate-200 px-4 py-3"
      >
        <Button
            v-if="selectedNode.type !== 'condition'"
            type="button"
            variant="outline"
            @click="emit('cancelInspector')"
        >
          Отменить
        </Button>
        <Button type="button" :disabled="!editable" @click="emit('commitInspector')">Сохранить</Button>
      </div>
    </DrawerContent>
  </Drawer>

  <ConditionEdgeSettingsDialog
      v-model:open="conditionSettingsOpenModel"
      :selected-edge="selectedEdge"
      :selected-edge-from-condition="selectedEdgeFromCondition"
      :condition-preview-question="conditionPreviewQuestion"
      :condition-preview-hide-title="conditionPreviewHideTitle"
      :condition-preview-content="conditionPreviewContent"
      :condition-preview-options="conditionPreviewOptions"
      :editable="editable"
      :copied-condition-variable-id="copiedConditionVariableId"
      @apply-logical="emit('applyConditionLogicalVariable', $event)"
      @update-edge="emit('updateConditionEdge', $event)"
  />

  <Suspense v-if="editingBlockId && scenarioId">
    <ScenarioBlockEditorDrawer
        v-model:open="blockEditorDrawerOpenModel"
        :scenario-id="scenarioId"
        :version-id="versionId"
        :block-id="editingBlockId"
        @update:block="emit('blockUpdate', $event)"
    />

    <template #fallback>
      <Teleport to="body">
        <div class="pointer-events-none fixed inset-0 z-[60] flex items-center justify-center" role="status">
          <div class="flex items-center gap-2 rounded-xl bg-white px-4 py-3 text-sm text-slate-600 shadow-lg ring-1 ring-slate-200">
            <Loader2 class="size-5 animate-spin" />
            Загрузка редактора…
          </div>
        </div>
      </Teleport>
    </template>
  </Suspense>

  <ActionNodeEditorDrawer
      v-if="selectedNode?.type === 'action'"
      v-model:open="actionEditorOpenModel"
      :node-id="selectedNode.id"
      :node-data="selectedNode.data"
      :editable="editable"
      :variables="variableEntries"
      :blocks="variableListBlocks"
      :user-variables="USER_VARIABLES"
      @update="emit('actionUpdate', $event)"
  />
</template>
