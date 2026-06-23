<script setup lang="ts">
import {computed, ref} from 'vue'
import {Check, Copy, Search} from 'lucide-vue-next'
import Tabs from '@/components/ui/tabs/Tabs.vue'
import TabsContent from '@/components/ui/tabs/TabsContent.vue'
import TabsList from '@/components/ui/tabs/TabsList.vue'
import TabsTrigger from '@/components/ui/tabs/TabsTrigger.vue'
import {type VarLike, implodeRef} from '@/modules/scenario/lib/scenario-variable-hints'

const props = defineProps<{
  v: VarLike
  copiedId: string | null
}>()

defineEmits<{ copy: [text: string, id: string] }>()

const search = ref('')
const options = computed(() => props.v.options ?? [])
const filteredOptions = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return options.value
  return options.value.filter((o) => o.value.toLowerCase().includes(q) || o.label.toLowerCase().includes(q))
})
</script>

<template>
  <Tabs default-value="options" class="w-full">
    <TabsList
        class="mx-3 mt-3 grid w-[calc(100%-1.5rem)]"
        :class="v.multiple ? 'grid-cols-2' : 'grid-cols-1'"
    >
      <TabsTrigger value="options" class="cursor-pointer text-[12px]">
        Опции
        <span
            v-if="options.length"
            class="ml-1.5 rounded-full bg-slate-100 px-1.5 py-px text-[9px] font-semibold text-slate-500"
        >{{ options.length }}</span>
      </TabsTrigger>
      <TabsTrigger v-if="v.multiple" value="templates" class="cursor-pointer text-[12px]">Шаблоны</TabsTrigger>
    </TabsList>

    <TabsContent value="options" class="m-0 flex max-h-72 flex-col">
      <div v-if="options.length > 8" class="border-b border-slate-100 px-3 py-2">
        <div class="relative">
          <Search class="pointer-events-none absolute left-2.5 top-1/2 size-3 -translate-y-1/2 text-slate-300"/>
          <input
              v-model="search"
              type="search"
              placeholder="Поиск опции..."
              class="h-7 w-full rounded-md border border-slate-200 bg-white pl-7 pr-2 text-[11px] placeholder:text-slate-300 focus:border-blue-400 focus:outline-none"
          />
        </div>
      </div>
      <div class="flex-1 overflow-y-auto p-1">
        <template v-if="options.length">
          <div
              v-for="opt in filteredOptions"
              :key="opt.value"
              class="flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2"
          >
            <div class="min-w-0 flex-1">
              <div class="truncate text-[12px] font-medium text-slate-700">{{ opt.label || opt.value }}</div>
              <div class="truncate font-mono text-[10px] text-slate-400">{{ opt.value }}</div>
            </div>
          </div>
          <div
              v-if="filteredOptions.length === 0"
              class="px-3 py-6 text-center text-[11px] text-slate-400"
          >Ничего не найдено
          </div>
        </template>
        <div v-else class="px-3 py-6 text-center text-[11px] text-slate-400">
          Опции не настроены
        </div>
      </div>
      <div class="border-t border-slate-100 bg-slate-50/60 px-3 py-2 text-[10px] text-slate-500">
        <span v-if="v.multiple">В переменной будет массив выбранных label-ов.</span>
        <span v-else>В переменной будет label выбранной опции.</span>
      </div>
    </TabsContent>

    <TabsContent v-if="v.multiple" value="templates" class="m-0 max-h-72 overflow-y-auto p-1">
      <button
          type="button"
          class="flex w-full flex-col gap-0.5 rounded-lg px-3 py-2 text-left transition hover:bg-slate-50"
          @click="$emit('copy', implodeRef(v), `${v.fieldId}:implode`)"
      >
        <div class="flex items-center justify-between gap-2">
          <span class="truncate text-[12px] font-medium text-slate-700">Объединить через запятую</span>
          <Check v-if="copiedId === `${v.fieldId}:implode`" class="size-3 shrink-0 text-emerald-500"/>
          <Copy v-else class="size-3 shrink-0 text-slate-300"/>
        </div>
        <div class="truncate font-mono text-[10px] text-slate-400">{{ implodeRef(v) }}</div>
        <div class="truncate text-[10px] text-slate-400">"Один, Два, Три"</div>
      </button>
    </TabsContent>
  </Tabs>
</template>
