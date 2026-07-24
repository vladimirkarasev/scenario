<script setup>
/* eslint-disable vue/no-mutating-props -- draft передаётся как reactive ссылка из родителя */
import {ref} from 'vue'
import {Check, Eye, Layers} from 'lucide-vue-next'
import {Label} from '@/components/ui/label'
import TiptapTextEditor from '@/modules/scenario/components/tiptap/TiptapTextEditor.vue'
import TiptapTextRenderer from '@/modules/scenario/components/tiptap/TiptapTextRenderer.vue'
import ScenarioVariableList from '@/modules/scenario/components/ScenarioVariableList.vue'

defineProps({
  node: {type: Object, required: true},
  draft: {type: Object, required: true},
  editable: {type: Boolean, default: false},
  variables: {type: Array, default: () => []},
  blocks: {type: Array, default: () => []},
})

const emit = defineEmits(['sync'])

const activeTab = ref('editor')
</script>

<template>
  <div class="flex h-full min-h-0">
    <!-- Variables sidebar -->
    <aside class="flex w-52 shrink-0 flex-col overflow-hidden border-r border-slate-200 bg-white">
      <div class="flex-1 space-y-3 overflow-y-auto p-3">
        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Переменные</div>
        <ScenarioVariableList :variables="variables" :blocks="blocks"/>
      </div>
    </aside>

    <main class="flex min-h-0 flex-1 flex-col overflow-hidden">
      <!-- Tab bar -->
      <div class="shrink-0 border-b border-slate-200 bg-white px-6 py-2.5">
        <div class="inline-flex items-center gap-1 rounded-lg bg-slate-100 p-1 text-[13px] font-medium">
          <button
              v-for="tab in [{ key: 'editor', label: 'Редактор', icon: Layers }, { key: 'preview', label: 'Предпросмотр', icon: Eye }]"
              :key="tab.key"
              type="button"
              class="inline-flex cursor-pointer items-center gap-1.5 rounded-md px-3 py-1 transition"
              :class="activeTab === tab.key
                            ? 'bg-white text-slate-900 shadow-sm'
                            : 'text-slate-500 hover:text-slate-700'"
              @click="activeTab = tab.key"
          >
            <component :is="tab.icon" class="size-3.5"/>
            {{ tab.label }}
          </button>
        </div>
      </div>

      <div class="flex-1 overflow-y-auto bg-slate-50">
        <!-- Editor -->
        <div v-show="activeTab === 'editor'" class="mx-auto max-w-2xl space-y-2 px-6 py-6">
          <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white px-4 py-3">
            <Label class="text-[10px] font-bold uppercase tracking-wider text-slate-400">ID ноды</Label>
            <p class="mt-1 select-all font-mono text-[12px] text-slate-700">{{ node.id }}</p>
          </div>

          <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
            <TiptapTextEditor
                v-model="draft.description"
                format="html"
                :editable="editable"
                min-height="min-h-32"
                placeholder="Что показать пользователю после завершения опроса?"
                @update:model-value="emit('sync')"
            />
          </div>
        </div>

        <!-- Preview -->
        <div v-if="activeTab === 'preview'" class="mx-auto max-w-2xl px-6 py-6">
          <div
              class="overflow-hidden rounded-2xl border border-emerald-200/80 bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04),0_4px_16px_rgba(0,0,0,0.04)]">
            <div class="h-[2px] bg-gradient-to-r from-emerald-400 to-emerald-500"/>
            <div class="px-5 py-5 text-left">
              <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-full bg-emerald-100">
                <Check class="size-5 text-emerald-600"/>
              </div>
              <TiptapTextRenderer
                  v-if="draft.description"
                  :html="draft.description"
                  class="prose prose-sm max-w-none text-[12.5px] text-slate-700"
              />
            </div>
          </div>
          <p class="mt-3 text-[11px] text-slate-400">
            Переменные показаны как есть — будут подставлены во время прохождения опроса.
          </p>
        </div>
      </div>
    </main>
  </div>
</template>
