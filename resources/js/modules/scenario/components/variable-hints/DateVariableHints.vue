<script setup lang="ts">
import {ref} from 'vue'
import {Check, Copy} from 'lucide-vue-next'
import Tabs from '@/components/ui/tabs/Tabs.vue'
import TabsContent from '@/components/ui/tabs/TabsContent.vue'
import TabsList from '@/components/ui/tabs/TabsList.vue'
import TabsTrigger from '@/components/ui/tabs/TabsTrigger.vue'
import {
  type VarLike,
  addTimeRef, dateFormatRef, dateFormatsFor, dateShiftsFor,
} from '@/modules/scenario/lib/scenario-variable-hints'

defineProps<{
  v: VarLike
  copiedId: string | null
}>()

const emit = defineEmits<{ copy: [text: string, id: string] }>()

const customFormat = ref('')
const customDuration = ref('')

function emitCopy(text: string, id: string): void {
  emit('copy', text, id)
}
</script>

<template>
  <Tabs default-value="formats" class="w-full">
    <TabsList class="mx-3 mt-3 grid w-[calc(100%-1.5rem)] grid-cols-3">
      <TabsTrigger value="formats" class="cursor-pointer text-[12px]">Форматы</TabsTrigger>
      <TabsTrigger value="shift" class="cursor-pointer text-[12px]">Сдвиг</TabsTrigger>
      <TabsTrigger value="custom" class="cursor-pointer text-[12px]">Свой</TabsTrigger>
    </TabsList>

    <TabsContent value="formats" class="m-0 max-h-72 overflow-y-auto p-1">
      <button
          v-for="f in dateFormatsFor(v)"
          :key="f.format"
          type="button"
          class="flex w-full flex-col gap-0.5 rounded-lg px-3 py-2 text-left transition hover:bg-slate-50"
          @click="emitCopy(dateFormatRef(v, f.format), `${v.fieldId}:fmt:${f.format}`)"
      >
        <div class="flex items-center justify-between gap-2">
          <span class="truncate text-[12px] font-medium text-slate-700">{{ f.name }}</span>
          <Check v-if="copiedId === `${v.fieldId}:fmt:${f.format}`" class="size-3 shrink-0 text-emerald-500"/>
          <Copy v-else class="size-3 shrink-0 text-slate-300"/>
        </div>
        <div class="truncate font-mono text-[10px] text-slate-400">{{ dateFormatRef(v, f.format) }}</div>
        <div class="truncate text-[10px] text-slate-400">{{ f.example }}</div>
      </button>
    </TabsContent>

    <TabsContent value="shift" class="m-0 flex max-h-72 flex-col">
      <div class="overflow-y-auto p-1">
        <button
            v-for="s in dateShiftsFor(v)"
            :key="s.duration"
            type="button"
            class="flex w-full flex-col gap-0.5 rounded-lg px-3 py-2 text-left transition hover:bg-slate-50"
            @click="emitCopy(addTimeRef(v, s.duration), `${v.fieldId}:sh:${s.duration}`)"
        >
          <div class="flex items-center justify-between gap-2">
            <span class="truncate text-[12px] font-medium text-slate-700">{{ s.name }}</span>
            <Check v-if="copiedId === `${v.fieldId}:sh:${s.duration}`" class="size-3 shrink-0 text-emerald-500"/>
            <Copy v-else class="size-3 shrink-0 text-slate-300"/>
          </div>
          <div class="truncate font-mono text-[10px] text-slate-400">{{ addTimeRef(v, s.duration) }}</div>
        </button>
      </div>
      <div class="border-t border-slate-100 bg-slate-50/60 p-3">
        <div class="mb-1.5 text-[10px] font-medium uppercase tracking-wider text-slate-500">Своя длительность</div>
        <div class="flex gap-1.5">
          <input
              v-model="customDuration"
              type="text"
              placeholder="например, 2d, 3h, -1mo"
              class="h-7 flex-1 rounded-md border border-slate-200 bg-white px-2 text-[11px] placeholder:text-slate-300 focus:border-blue-400 focus:outline-none"
          />
          <button
              type="button"
              :disabled="!customDuration.trim()"
              class="inline-flex h-7 items-center gap-1 rounded-md bg-blue-600 px-2.5 text-[11px] font-medium text-white transition hover:bg-blue-700 disabled:opacity-40"
              @click="emitCopy(addTimeRef(v, customDuration.trim()), `${v.fieldId}:sh:custom`)"
          >
            <Check v-if="copiedId === `${v.fieldId}:sh:custom`" class="size-3"/>
            <Copy v-else class="size-3"/>
            Копировать
          </button>
        </div>
        <div class="mt-1 text-[10px] text-slate-400">Единицы: y, mo, w, d, h, m, s. Можно с минусом.</div>
      </div>
    </TabsContent>

    <TabsContent value="custom" class="m-0 p-3 space-y-2">
      <div class="text-[10px] font-medium uppercase tracking-wider text-slate-500">Свой формат</div>
      <input
          v-model="customFormat"
          type="text"
          placeholder="DD.MM.YYYY"
          class="h-8 w-full rounded-md border border-slate-200 bg-white px-2.5 text-[12px] placeholder:text-slate-300 focus:border-blue-400 focus:outline-none"
      />
      <div class="rounded-md bg-slate-50 px-2.5 py-1.5 text-[10px] text-slate-500">
        Доступны токены moment.js: <span class="font-mono">DD MM YYYY HH mm ss MMMM dddd</span> и т.д.
      </div>
      <button
          type="button"
          :disabled="!customFormat.trim()"
          class="inline-flex h-8 w-full items-center justify-center gap-1.5 rounded-md bg-blue-600 px-3 text-[12px] font-medium text-white transition hover:bg-blue-700 disabled:opacity-40"
          @click="emitCopy(dateFormatRef(v, customFormat.trim()), `${v.fieldId}:fmt:custom`)"
      >
        <Check v-if="copiedId === `${v.fieldId}:fmt:custom`" class="size-3.5"/>
        <Copy v-else class="size-3.5"/>
        Копировать
      </button>
      <div v-if="customFormat.trim()"
           class="truncate rounded-md border border-dashed border-slate-200 px-2.5 py-1.5 font-mono text-[10px] text-slate-500">
        {{ dateFormatRef(v, customFormat.trim()) }}
      </div>
    </TabsContent>
  </Tabs>
</template>
