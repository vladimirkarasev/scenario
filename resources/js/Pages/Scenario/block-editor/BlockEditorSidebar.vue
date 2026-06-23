<script setup>
import {Check, Copy} from 'lucide-vue-next'
import {USER_VARIABLES} from '@/modules/scenario/lib/scenario-flow-constants'

defineProps({
  fieldGroups: {type: Array, required: true},
  canManageCatalog: {type: Boolean, default: false},
  versionDocument: {type: Object, required: true},
  allVariables: {type: Array, required: true},
  blockId: {type: String, required: true},
  copiedUserId: {type: String, default: null},
  copiedVarId: {type: String, default: null},
})

defineEmits(['add-field', 'copy-user', 'copy-block'])
</script>

<template>
  <aside class="flex w-56 shrink-0 flex-col overflow-hidden border-r border-slate-200 bg-white">
    <div class="shrink-0 border-b border-slate-100 px-3 py-2.5">
      <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Добавить поле</div>
    </div>

    <div class="flex-1 overflow-y-auto">
      <div class="space-y-4 p-3">
        <div v-for="group in fieldGroups" :key="group.title" class="space-y-1.5">
          <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">{{ group.title }}</div>
          <div class="grid grid-cols-2 gap-1">
            <button
                v-for="fieldType in group.items"
                :key="fieldType.type"
                type="button"
                class="flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-2 py-2 text-left transition hover:border-cyan-200 hover:bg-cyan-50 disabled:pointer-events-none disabled:opacity-40"
                :disabled="!canManageCatalog"
                @click="$emit('add-field', fieldType.type)"
            >
              <component :is="fieldType.icon" class="size-3.5 shrink-0 text-slate-500"/>
              <span class="truncate text-[11px] font-medium text-slate-700">{{ fieldType.label }}</span>
            </button>
          </div>
        </div>
      </div>

      <div class="border-t border-slate-100 p-3 space-y-3">
        <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Переменные</div>

        <div class="space-y-0.5">
          <div class="mb-1 text-[10px] font-medium text-slate-500">Пользователь</div>
          <button
              v-for="v in USER_VARIABLES"
              :key="v.id"
              type="button"
              class="flex w-full items-center justify-between gap-2 rounded-md px-2 py-1 text-left transition hover:bg-slate-50"
              @click="$emit('copy-user', v)"
          >
            <span class="truncate font-mono text-[10px] text-slate-500">{{ v.name }}</span>
            <Check v-if="copiedUserId === v.id" class="size-3 shrink-0 text-emerald-500"/>
            <Copy v-else class="size-3 shrink-0 text-slate-300"/>
          </button>
        </div>

        <template v-if="allVariables.length">
          <template
              v-for="block in versionDocument.blocks.filter((b) => b.type === 'block' && allVariables.some((v) => v.blockId === b.id))"
              :key="block.id"
          >
            <div class="space-y-0.5">
              <div class="mb-1 flex items-center gap-1.5">
                <span class="truncate text-[10px] font-medium text-slate-500">{{ block.data.title || block.id }}</span>
                <span v-if="block.id === blockId"
                      class="shrink-0 rounded-full bg-cyan-100 px-1 py-px text-[9px] font-medium text-cyan-600">current</span>
              </div>
              <button
                  v-for="v in allVariables.filter((vv) => vv.blockId === block.id)"
                  :key="v.fieldId"
                  type="button"
                  class="flex w-full items-center justify-between gap-2 rounded-md px-2 py-1 text-left transition hover:bg-slate-50"
                  @click="$emit('copy-block', v)"
              >
                <span class="truncate font-mono text-[10px] text-slate-500">{{ v.name }}</span>
                <Check v-if="copiedVarId === v.fieldId" class="size-3 shrink-0 text-emerald-500"/>
                <Copy v-else class="size-3 shrink-0 text-slate-300"/>
              </button>
            </div>
          </template>
        </template>
      </div>
    </div>
  </aside>
</template>
