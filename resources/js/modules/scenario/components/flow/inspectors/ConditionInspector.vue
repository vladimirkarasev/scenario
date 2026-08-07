<script setup lang="ts">
/* eslint-disable vue/no-mutating-props -- draft передаётся как reactive ссылка из родителя */
import {Label} from '@/components/ui/label'
import {Textarea} from '@/components/ui/textarea'
import ScenarioVariableList from '@/modules/scenario/components/ScenarioVariableList.vue'
import TiptapTextEditor from '@/modules/scenario/components/tiptap/TiptapTextEditor.vue'
import NodeTitleSettings from '@/modules/scenario/components/flow/NodeTitleSettings.vue'
import type {LinkedBlockEntry} from '@/modules/scenario/composables/useLinkedScenarioVariables'
import type {VariableListBlock} from '@/modules/scenario/composables/useScenarioVariables'
import type {ScenarioFlowNode} from '@/modules/scenario/types/scenario-flow-editor'
import type {VariableEntry} from '@/modules/scenario/types/scenario-variable-entry'

defineProps<{
  node: ScenarioFlowNode
  draft: Record<string, unknown>
  editable: boolean
  variables: VariableEntry[]
  blocks: Array<VariableListBlock | LinkedBlockEntry>
}>()

const emit = defineEmits<{
  sync: []
}>()
</script>

<template>
  <div class="flex h-full min-h-0">
    <aside class="flex w-56 shrink-0 flex-col overflow-hidden border-r border-slate-200 bg-white">
      <div class="flex-1 space-y-3 overflow-y-auto p-3">
        <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Переменные</div>
        <ScenarioVariableList :variables="variables" :blocks="blocks" />
      </div>
    </aside>

    <main class="flex-1 overflow-y-auto bg-slate-50 py-8">
      <div class="mx-auto w-full max-w-5xl space-y-2.5 px-6">
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
          <div class="space-y-4 p-4">
            <div class="space-y-1.5">
              <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">ID ноды</Label>
              <p class="select-all rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-[12px] text-slate-700">
                {{ node.id }}
              </p>
            </div>

            <NodeTitleSettings
                v-model:title="draft.title"
                v-model:hide-title="draft.hideTitle"
                :editable="editable"
                @change="emit('sync')"
            />

            <div class="space-y-1.5">
              <Label for="node-value" class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                Значение
              </Label>
              <Textarea
                  id="node-value"
                  v-model="draft.value"
                  rows="4"
                  class="border-slate-200 font-mono text-sm"
                  :disabled="!editable"
                  @update:model-value="emit('sync')"
              />
            </div>
          </div>
        </div>

        <div class="space-y-2 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <div>
            <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
              Контент условия
            </Label>
            <p class="mt-1 text-xs leading-5 text-slate-400">
              Этот текст увидит пользователь перед выбором ветки.
            </p>
          </div>
          <TiptapTextEditor
              v-model="draft.content"
              :editable="editable"
              min-height="min-h-40"
              placeholder="Опишите условие или задайте вопрос пользователю..."
              @update:model-value="emit('sync')"
          />
        </div>
      </div>
    </main>
  </div>
</template>
