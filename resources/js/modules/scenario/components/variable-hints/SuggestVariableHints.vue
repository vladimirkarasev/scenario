<script setup lang="ts">
import {computed, ref} from 'vue'
import {Check, Copy, Loader2, Search} from 'lucide-vue-next'
import type {WebhookField} from '@/modules/proxy/types/webhook'
import {type VarLike, accessorRef} from '@/modules/scenario/lib/scenario-variable-hints'

const props = defineProps<{
  v: VarLike
  copiedId: string | null
  fields: WebhookField[]
  loading: boolean
}>()

defineEmits<{ copy: [text: string, id: string] }>()

const search = ref('')
const filteredFields = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return props.fields
  return props.fields.filter((f) => f.key.toLowerCase().includes(q) || f.label.toLowerCase().includes(q))
})
</script>

<template>
  <div class="flex max-h-72 flex-col">
    <div class="border-b border-slate-100 px-3 py-2">
      <div class="relative">
        <Search class="pointer-events-none absolute left-2.5 top-1/2 size-3 -translate-y-1/2 text-slate-300"/>
        <input
            v-model="search"
            type="search"
            placeholder="Поиск поля..."
            class="h-7 w-full rounded-md border border-slate-200 bg-white pl-7 pr-2 text-[11px] placeholder:text-slate-300 focus:border-blue-400 focus:outline-none"
        />
      </div>
    </div>
    <div class="flex-1 overflow-y-auto p-1">
      <div v-if="loading" class="flex items-center justify-center gap-2 py-6 text-[11px] text-slate-400">
        <Loader2 class="size-3 animate-spin"/>
        Загрузка полей...
      </div>
      <template v-else-if="fields.length">
        <button
            v-for="f in filteredFields"
            :key="f.key"
            type="button"
            class="flex w-full flex-col gap-0.5 rounded-lg px-3 py-2 text-left transition hover:bg-slate-50"
            @click="$emit('copy', accessorRef(v, f.key), `${v.fieldId}:${f.key}`)"
        >
          <div class="flex items-center justify-between gap-2">
            <span class="truncate text-[12px] font-medium text-slate-700">{{ f.label || f.key }}</span>
            <Check v-if="copiedId === `${v.fieldId}:${f.key}`" class="size-3 shrink-0 text-emerald-500"/>
            <Copy v-else class="size-3 shrink-0 text-slate-300"/>
          </div>
          <div class="truncate font-mono text-[10px] text-slate-400">{{ accessorRef(v, f.key) }}</div>
        </button>
        <div
            v-if="filteredFields.length === 0"
            class="px-3 py-6 text-center text-[11px] text-slate-400"
        >Ничего не найдено
        </div>
      </template>
      <div v-else class="px-3 py-6 text-center text-[11px] text-slate-400">
        У интеграции нет описанных полей результата
      </div>
    </div>
  </div>
</template>
