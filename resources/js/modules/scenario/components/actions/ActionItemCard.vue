<script setup lang="ts">
/* eslint-disable vue/no-mutating-props -- item передаётся как reactive ссылка из draft родителя */
import {Input} from '@/components/ui/input'
import {Label} from '@/components/ui/label'
import {ChevronsUpDown, GripVertical, Trash2} from 'lucide-vue-next'
import type {ActionInputField} from '@/modules/actions/types/action'
import {formatBackoffInput, parseBackoffInput} from '@/modules/actions/lib/backoff'

interface ActionItem {
  id: string
  code: string
  action_code: string
  action_id: string
  name: string
  input: Record<string, unknown>
  backoff: number[]
  delay_before: number
}

type Variant = 'main' | 'before' | 'error'

const props = withDefaults(defineProps<{
  item: ActionItem
  index: number
  variant?: Variant
  editable?: boolean
  loadingActions?: boolean
  fields: ActionInputField[]
  actionLabel: (id: string, placeholder: string) => string
  actionCode: (id: string) => string
}>(), {
  variant: 'main',
  editable: true,
  loadingActions: false,
})

defineEmits<{
  remove: [id: string]
  openPicker: [target: `item:${string}`]
}>()

const STYLES: Record<Variant, { bg: string; badge: string; gripVisible: boolean }> = {
  main: {bg: 'bg-slate-50', badge: 'bg-violet-100 text-violet-600', gripVisible: true},
  before: {bg: 'bg-emerald-50/30', badge: 'bg-emerald-100 text-emerald-700', gripVisible: false},
  error: {bg: 'bg-red-50/30', badge: 'bg-red-100 text-red-700', gripVisible: false},
}
const style = STYLES[props.variant]
</script>

<template>
  <div class="space-y-3 rounded-xl border border-slate-200 p-3" :class="style.bg">
    <div class="flex items-center gap-2">
      <GripVertical v-if="style.gripVisible" class="size-3.5 shrink-0 text-slate-300"/>
      <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[10px] font-bold"
            :class="style.badge">
                {{ index + 1 }}
            </span>
      <div class="min-w-0 flex-1">
        <Input v-model="item.name" :disabled="!editable" class="h-7 text-xs" placeholder="Название (опционально)"/>
      </div>
      <button
          v-if="editable"
          type="button"
          class="shrink-0 rounded-lg p-1 text-slate-400 transition hover:bg-red-50 hover:text-red-500"
          @click="$emit('remove', item.id)"
      >
        <Trash2 class="size-3.5"/>
      </button>
    </div>

    <div class="grid grid-cols-[1fr_140px] gap-2">
      <div class="space-y-1">
        <Label class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Action</Label>
        <button
            type="button"
            class="flex h-8 w-full items-center justify-between gap-2 rounded-lg border border-slate-200 bg-white px-2.5 text-xs transition hover:border-slate-300 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="!editable || loadingActions"
            @click="$emit('openPicker', `item:${item.id}`)"
        >
                    <span :class="item.action_id ? 'text-slate-800' : 'text-slate-400'">
                        {{ actionLabel(item.action_id, loadingActions ? 'Загрузка...' : '— выбрать action —') }}
                    </span>
          <ChevronsUpDown class="size-3 shrink-0 text-slate-400"/>
        </button>
      </div>
      <div class="space-y-1">
        <Label class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Code (scope)</Label>
        <Input v-model="item.code" :disabled="!editable" placeholder="пусто = глобально" class="h-8 font-mono text-xs"/>
      </div>
    </div>

    <div class="grid grid-cols-[1fr_100px] gap-2">
      <div class="space-y-1">
        <Label class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Backoff (сек, через запятую)</Label>
        <Input
            :model-value="formatBackoffInput(item.backoff)"
            :disabled="!editable"
            placeholder="0,60,180"
            class="h-8 font-mono text-xs"
            @update:model-value="(v: string) => item.backoff = parseBackoffInput(v)"
        />
      </div>
      <div class="space-y-1">
        <Label class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Delay before (сек)</Label>
        <Input
            :model-value="item.delay_before" type="number" min="0" step="1"
            :disabled="!editable" class="h-8 text-xs"
            @update:model-value="(v: string | number) => item.delay_before = Math.max(0, Number(v) || 0)"
        />
      </div>
    </div>

    <template v-if="item.action_id && fields.length">
      <div class="space-y-2">
        <Label class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Input</Label>
        <div class="space-y-1.5">
          <div v-for="field in fields" :key="field.key" class="flex items-center gap-2">
                        <span class="w-28 shrink-0 truncate text-[11px] text-slate-500"
                              :title="field.label || field.key">
                            {{ field.label || field.key }}
                            <span v-if="field.required" class="text-red-400">*</span>
                        </span>
            <Input v-model="item.input[field.key] as string" :disabled="!editable"
                   :placeholder="String(field.default ?? '')" class="h-7 flex-1 text-xs"/>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
