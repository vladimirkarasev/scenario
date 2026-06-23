<script setup>
/* eslint-disable vue/no-mutating-props -- draft передаётся как reactive ссылка из родителя */
import {Button} from '@/components/ui/button'
import {Input} from '@/components/ui/input'
import {Label} from '@/components/ui/label'
import {Separator} from '@/components/ui/separator'
import DefaultInspector from './DefaultInspector.vue'

defineProps({
  node: {type: Object, required: true},
  draft: {type: Object, required: true},
  editable: {type: Boolean, default: false},
  scenarioId: {type: String, default: null},
  versionId: {type: String, default: null},
})

const emit = defineEmits(['sync', 'updateVariable', 'openEditor'])
</script>

<template>
  <div class="space-y-4">
    <DefaultInspector :node="node"/>

    <Separator class="bg-slate-200"/>

    <div class="space-y-2">
      <Label for="block-variable">Переменная</Label>
      <Input
          id="block-variable"
          :model-value="draft.variable"
          class="border-slate-200"
          placeholder="название_блока"
          :disabled="!editable"
          @update:model-value="emit('updateVariable', $event)"
      />
    </div>

    <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm">
      <input
          v-model="draft.skipInSurvey"
          type="checkbox"
          class="mt-0.5 size-4 rounded border-slate-300"
          :disabled="!editable"
          @change="emit('sync')"
      />
      <span class="grid gap-0.5">
                <span class="font-medium text-slate-800">Пропустить блок в опросе</span>
                <span class="text-xs leading-5 text-slate-500">Блок сохранится в схеме, но при прохождении сценария будет автоматически пропущен.</span>
            </span>
    </label>

    <Separator class="bg-slate-200"/>

    <div class="rounded-2xl border border-blue-100 bg-blue-50 p-3">
      <div class="flex flex-col gap-3">
        <div class="text-sm font-medium text-blue-900">Редактор полей</div>
        <div class="text-xs leading-5 text-blue-800/70">
          Поля шага редактируются на отдельной странице.
        </div>
        <Button v-if="versionId || scenarioId" class="w-full gap-2" @click="emit('openEditor')">
          Редактировать
        </Button>
      </div>
    </div>
  </div>
</template>
