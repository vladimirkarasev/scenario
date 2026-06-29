<script setup lang="ts">
import {Check, Copy} from 'lucide-vue-next'
import {type VarLike, accessorRef, PHONE_ACCESSORS} from '@/modules/scenario/lib/scenario-variable-hints'

defineProps<{
  v: VarLike
  copiedId: string | null
}>()

defineEmits<{ copy: [text: string, id: string] }>()
</script>

<template>
  <div class="max-h-72 overflow-y-auto p-1">
    <button
        v-for="a in PHONE_ACCESSORS"
        :key="a.suffix"
        type="button"
        class="flex w-full flex-col gap-0.5 rounded-lg px-3 py-2 text-left transition hover:bg-slate-50"
        @click="$emit('copy', accessorRef(v, a.suffix), `${v.fieldId}:ph:${a.suffix}`)"
    >
      <div class="flex items-center justify-between gap-2">
        <span class="truncate text-[12px] font-medium text-slate-700">{{ a.name }}</span>
        <Check v-if="copiedId === `${v.fieldId}:ph:${a.suffix}`" class="size-3 shrink-0 text-emerald-500"/>
        <Copy v-else class="size-3 shrink-0 text-slate-300"/>
      </div>
      <div class="truncate font-mono text-[10px] text-slate-400">{{ accessorRef(v, a.suffix) }}</div>
      <div class="truncate text-[10px] text-slate-400">{{ a.description }}</div>
    </button>
  </div>
</template>
