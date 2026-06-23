<script setup lang="ts">
import {computed, ref} from 'vue'
import {Check, Copy, Loader2, Search} from 'lucide-vue-next'
import Tabs from '@/components/ui/tabs/Tabs.vue'
import TabsContent from '@/components/ui/tabs/TabsContent.vue'
import TabsList from '@/components/ui/tabs/TabsList.vue'
import TabsTrigger from '@/components/ui/tabs/TabsTrigger.vue'
import type {DirectorySchemaField} from '@/modules/directories/types/directory'
import {
  type VarLike, STRUCTURE_ITEMS, accessorRef,
} from '@/modules/scenario/lib/scenario-variable-hints'

const props = defineProps<{
  v: VarLike
  copiedId: string | null
  schema: DirectorySchemaField[]
  loading: boolean
}>()

defineEmits<{ copy: [text: string, id: string] }>()

const search = ref('')
const filteredColumns = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return props.schema
  return props.schema.filter((c) => c.key.toLowerCase().includes(q) || c.name.toLowerCase().includes(q))
})
</script>

<template>
  <Tabs default-value="structure" class="w-full">
    <TabsList class="mx-3 mt-3 grid w-[calc(100%-1.5rem)] grid-cols-2">
      <TabsTrigger value="structure" class="cursor-pointer text-[12px]">Структура</TabsTrigger>
      <TabsTrigger value="columns" class="cursor-pointer text-[12px]">
        Колонки
        <span
            v-if="schema.length"
            class="ml-1.5 rounded-full bg-slate-100 px-1.5 py-px text-[9px] font-semibold text-slate-500"
        >{{ schema.length }}</span>
      </TabsTrigger>
    </TabsList>

    <TabsContent value="structure" class="m-0 max-h-72 overflow-y-auto p-1">
      <button
          v-for="item in STRUCTURE_ITEMS"
          :key="item.suffix"
          type="button"
          class="flex w-full flex-col gap-0.5 rounded-lg px-3 py-2 text-left transition hover:bg-slate-50"
          @click="$emit('copy', accessorRef(v, item.suffix), `${v.fieldId}:${item.suffix}`)"
      >
        <div class="flex items-center justify-between gap-2">
          <span class="truncate text-[12px] font-medium text-slate-700">{{ item.name }}</span>
          <Check v-if="copiedId === `${v.fieldId}:${item.suffix}`" class="size-3 shrink-0 text-emerald-500"/>
          <Copy v-else class="size-3 shrink-0 text-slate-300"/>
        </div>
        <div class="truncate font-mono text-[10px] text-slate-400">{{ accessorRef(v, item.suffix) }}</div>
        <div class="truncate text-[10px] text-slate-400">{{ item.description }}</div>
      </button>
    </TabsContent>

    <TabsContent value="columns" class="m-0 flex max-h-72 flex-col">
      <div class="border-b border-slate-100 px-3 py-2">
        <div class="relative">
          <Search class="pointer-events-none absolute left-2.5 top-1/2 size-3 -translate-y-1/2 text-slate-300"/>
          <input
              v-model="search"
              type="search"
              placeholder="Поиск колонки..."
              class="h-7 w-full rounded-md border border-slate-200 bg-white pl-7 pr-2 text-[11px] placeholder:text-slate-300 focus:border-blue-400 focus:outline-none"
          />
        </div>
      </div>
      <div class="flex-1 overflow-y-auto p-1">
        <div v-if="loading" class="flex items-center justify-center gap-2 py-6 text-[11px] text-slate-400">
          <Loader2 class="size-3 animate-spin"/>
          Загрузка схемы...
        </div>
        <template v-else-if="schema.length">
          <button
              v-for="col in filteredColumns"
              :key="col.key"
              type="button"
              class="flex w-full flex-col gap-0.5 rounded-lg px-3 py-2 text-left transition hover:bg-slate-50"
              @click="$emit('copy', accessorRef(v, `data.${col.key}`), `${v.fieldId}:data.${col.key}`)"
          >
            <div class="flex items-center justify-between gap-2">
              <span class="truncate text-[12px] font-medium text-slate-700">{{ col.name || col.key }}</span>
              <Check v-if="copiedId === `${v.fieldId}:data.${col.key}`" class="size-3 shrink-0 text-emerald-500"/>
              <Copy v-else class="size-3 shrink-0 text-slate-300"/>
            </div>
            <div class="truncate font-mono text-[10px] text-slate-400">{{ accessorRef(v, `data.${col.key}`) }}</div>
          </button>
          <div
              v-if="filteredColumns.length === 0"
              class="px-3 py-6 text-center text-[11px] text-slate-400"
          >Ничего не найдено
          </div>
        </template>
        <div v-else class="px-3 py-6 text-center text-[11px] text-slate-400">
          У справочника нет колонок
        </div>
      </div>
    </TabsContent>
  </Tabs>
</template>
