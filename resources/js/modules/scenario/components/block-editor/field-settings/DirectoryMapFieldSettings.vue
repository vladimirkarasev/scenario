<script lang="ts">
import {markRaw} from 'vue'
import {Map as MapIcon} from 'lucide-vue-next'

export const fieldMeta = {type: 'directory_map', label: 'Карта', icon: markRaw(MapIcon)}
</script>

<script setup lang="ts">
import {computed, ref} from 'vue'
import {Select, SelectContent, SelectItem, SelectTrigger, SelectValue} from '@/components/ui/select'
import {Input} from '@/components/ui/input'
import {ChevronsUpDown, Check, Copy, X} from 'lucide-vue-next'
import DirectoryPickerDialog from '@/modules/directories/components/DirectoryPickerDialog.vue'
import TiptapTextEditor from '@/modules/scenario/components/tiptap/TiptapTextEditor.vue'
import {useDirectorySchemaLoader} from '@/modules/directories/composables/useDirectorySchemaLoader'
import type {Directory} from '@/modules/directories/types/directory'
import type {DirectoryMapBlockField} from '../../../lib/scenario-block-fields'

const props = defineProps<{ field: DirectoryMapBlockField; disabled?: boolean }>()
const emit = defineEmits<{ update: [patch: Partial<DirectoryMapBlockField>] }>()
defineOptions({inheritAttrs: false})

const pickerOpen = ref(false)
const directoryName = ref('')

const {versions, schemaFields, versionLabel} = useDirectorySchemaLoader(
    computed(() => props.field.directoryId),
    computed(() => props.field.versionId),
    (id) => emit('update', {versionId: id}),
)

const coordinateFields = computed(() => schemaFields.value.filter((f) => f.type === 'string' || f.type === 'integer'))

function onDirectorySelect(directory: Directory): void {
  directoryName.value = directory.name
  const activeId = directory.active_version ? String(directory.active_version.id) : ''
  emit('update', {directoryId: directory.id, versionId: activeId, latKey: '', lngKey: '', fields: []})
}

function clearDirectory(): void {
  emit('update', {directoryId: '', versionId: '', latKey: '', lngKey: '', fields: []})
}

function onVersionChange(versionId: string): void {
  emit('update', {versionId, latKey: '', lngKey: '', fields: []})
}

const copiedKey = ref<string | null>(null)

function tokenLabel(key: string): string {
  return `{{ ${key} }}`
}

async function copyToken(key: string): Promise<void> {
  await navigator.clipboard.writeText(tokenLabel(key))
  copiedKey.value = key
  setTimeout(() => {
    copiedKey.value = null
  }, 1500)
}
</script>

<template>
  <div class="space-y-3">
    <div class="space-y-1.5">
      <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">Справочник</label>
      <div class="flex items-center gap-1.5">
        <button
            type="button"
            class="flex h-8 flex-1 items-center justify-between gap-2 rounded-lg border border-slate-200 bg-white px-2.5 text-xs transition hover:border-slate-300 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="disabled"
            @click="pickerOpen = true"
        >
          <span :class="field.directoryId ? 'text-slate-800' : 'text-slate-400'">
            {{ field.directoryId ? (directoryName || 'Загрузка...') : '— не выбрано —' }}
          </span>
          <ChevronsUpDown class="size-3 shrink-0 text-slate-400"/>
        </button>
        <button
            v-if="field.directoryId && !disabled"
            type="button"
            class="flex size-8 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-400 transition hover:border-slate-300 hover:text-slate-600"
            @click="clearDirectory"
        >
          <X class="size-3.5"/>
        </button>
      </div>
    </div>

    <div v-if="field.directoryId && versions.length" class="space-y-1.5">
      <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">Версия</label>
      <Select :model-value="field.versionId" :disabled="disabled" @update:model-value="onVersionChange($event)">
        <SelectTrigger class="w-full rounded-xl border-slate-200 text-xs" size="sm">
          <SelectValue placeholder="Загрузка..."/>
        </SelectTrigger>
        <SelectContent>
          <SelectItem v-for="v in versions" :key="v.id" :value="String(v.id)">{{ versionLabel(v) }}</SelectItem>
        </SelectContent>
      </Select>
    </div>

    <div v-if="field.directoryId" class="grid grid-cols-2 gap-3">
      <div class="space-y-1.5">
        <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">Колонка широты</label>
        <Select :model-value="field.latKey" :disabled="disabled" @update:model-value="emit('update', { latKey: $event })">
          <SelectTrigger class="w-full rounded-lg border-slate-200 text-xs" size="sm">
            <SelectValue placeholder="Выберите колонку..."/>
          </SelectTrigger>
          <SelectContent>
            <SelectItem v-for="f in coordinateFields" :key="f.key" :value="f.key">{{ f.name }}</SelectItem>
          </SelectContent>
        </Select>
      </div>
      <div class="space-y-1.5">
        <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">Колонка долготы</label>
        <Select :model-value="field.lngKey" :disabled="disabled" @update:model-value="emit('update', { lngKey: $event })">
          <SelectTrigger class="w-full rounded-lg border-slate-200 text-xs" size="sm">
            <SelectValue placeholder="Выберите колонку..."/>
          </SelectTrigger>
          <SelectContent>
            <SelectItem v-for="f in coordinateFields" :key="f.key" :value="f.key">{{ f.name }}</SelectItem>
          </SelectContent>
        </Select>
      </div>
    </div>
    <p v-if="field.directoryId" class="text-[11px] text-slate-400">
      Убедитесь, что выбранные колонки содержат числовые координаты — тип колонки в схеме справочника это не гарантирует.
    </p>

    <div class="space-y-1.5">
      <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">Масштаб по умолчанию</label>
      <Input
          :model-value="field.defaultZoom"
          type="number"
          min="1"
          max="19"
          class="h-8 text-sm"
          :disabled="disabled"
          @update:model-value="emit('update', { defaultZoom: Number($event) || 12 })"
      />
    </div>

    <div v-if="field.directoryId" class="space-y-1.5">
      <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">Карточка детальной информации</label>
      <div v-if="schemaFields.length" class="flex flex-wrap gap-1">
        <button
            v-for="col in schemaFields"
            :key="col.key"
            type="button"
            class="inline-flex items-center gap-1 rounded border border-slate-200 bg-slate-50 px-1.5 py-0.5 font-mono text-[10px] text-slate-600 transition hover:border-slate-300 hover:bg-slate-100"
            :disabled="disabled"
            @click="copyToken(col.key)"
        >
          {{ tokenLabel(col.key) }}
          <Check v-if="copiedKey === col.key" class="size-2.5 text-emerald-500"/>
          <Copy v-else class="size-2.5 text-slate-300"/>
        </button>
      </div>
      <div class="rounded-xl border border-slate-200">
        <TiptapTextEditor
            :model-value="field.detailDocument"
            format="json"
            min-height="min-h-32"
            placeholder="Шаблон карточки — вставьте токены колонок выше..."
            @update:model-value="emit('update', { detailDocument: $event })"
        />
      </div>
    </div>
  </div>

  <DirectoryPickerDialog
      :open="pickerOpen"
      :selected-id="field.directoryId || undefined"
      @update:open="pickerOpen = $event"
      @select="onDirectorySelect"
  />
</template>
