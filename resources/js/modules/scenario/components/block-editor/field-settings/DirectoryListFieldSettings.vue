<script lang="ts">
import {markRaw} from 'vue'
import {List} from 'lucide-vue-next'

export const fieldMeta = {type: 'directory_list', label: 'Список', icon: markRaw(List)}
</script>

<script setup lang="ts">
import {computed, ref} from 'vue'
import {Select, SelectContent, SelectItem, SelectTrigger, SelectValue} from '@/components/ui/select'
import {Input} from '@/components/ui/input'
import {ChevronsUpDown, X} from 'lucide-vue-next'
import DirectoryPickerDialog from '@/modules/scenario/components/pickers/DirectoryPickerDialog.vue'
import DirectoryLabelTemplateField from './DirectoryLabelTemplateField.vue'
import {useDirectorySchemaLoader} from '@/modules/directories/composables/useDirectorySchemaLoader'
import type {Directory} from '@/modules/directories/types/directory'
import type {DirectoryListBlockField, DirectoryListDepDrop} from '../../../lib/scenario-block-fields'

const props = defineProps<{
  field: DirectoryListBlockField
  disabled?: boolean
  blockFields?: DirectoryListBlockField[]
}>()
const emit = defineEmits<{ update: [patch: Partial<DirectoryListBlockField>] }>()
defineOptions({inheritAttrs: false})
const pickerOpen = ref(false)

const {directoryName, versions, schemaFields, versionLabel} = useDirectorySchemaLoader(
    computed(() => props.field.directoryId),
    computed(() => props.field.versionId),
    (id) => emit('update', {versionId: id}),
)

function onVersionChange(versionId: string): void {
  emit('update', {versionId, labelTemplate: ''})
}

function onDirectorySelect(directory: Directory): void {
  directoryName.value = directory.name
  const activeId = directory.active_version ? String(directory.active_version.id) : ''
  emit('update', {directoryId: directory.id, versionId: activeId, labelTemplate: ''})
}

function clearDirectory(): void {
  emit('update', {directoryId: '', versionId: '', labelTemplate: ''})
}

// ── DepDrop ────────────────────────────────────────────────────────────────────

const depDropEnabled = computed(() => Boolean(props.field.depDrop))

const candidateFields = computed(() =>
    (props.blockFields ?? []).filter(
        (f) => f.id !== props.field.id && f.type === 'directory_list' && f.varName,
    ),
)

function enableDepDrop(): void {
  emit('update', {
    depDrop: {fieldVarName: '', filterKey: '', valueKey: 'external_key'},
  })
}

function disableDepDrop(): void {
  emit('update', {depDrop: null})
}

function patchDepDrop(patch: Partial<DirectoryListDepDrop>): void {
  if (!props.field.depDrop) return
  emit('update', {depDrop: {...props.field.depDrop, ...patch}})
}
</script>

<template>
  <div class="space-y-3">
    <!-- Directory picker -->
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

    <!-- Version selector -->
    <div v-if="field.directoryId && versions.length" class="space-y-1.5">
      <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">Версия</label>
      <Select
          :model-value="field.versionId"
          :disabled="disabled"
          @update:model-value="onVersionChange($event)"
      >
        <SelectTrigger class="w-full rounded-xl border-slate-200 text-xs" size="sm">
          <SelectValue placeholder="Загрузка..."/>
        </SelectTrigger>
        <SelectContent>
          <SelectItem v-for="v in versions" :key="v.id" :value="String(v.id)">
            {{ versionLabel(v) }}
          </SelectItem>
        </SelectContent>
      </Select>
    </div>

    <!-- Label template -->
    <DirectoryLabelTemplateField
        v-if="field.directoryId"
        :model-value="field.labelTemplate"
        :fields="schemaFields"
        :disabled="disabled"
        @update:model-value="emit('update', { labelTemplate: $event })"
    />

    <div class="space-y-1.5">
      <label class="flex h-9 items-center gap-2.5 rounded-xl border border-slate-200 bg-slate-50 px-3">
        <input
            :checked="Boolean(field.multiple)"
            type="checkbox"
            class="size-3.5 rounded border-slate-300"
            :disabled="disabled"
            @change="emit('update', { multiple: ($event.target as HTMLInputElement).checked })"
        />
        <span class="text-xs text-slate-700">Мультивыбор</span>
      </label>
      <label class="flex h-9 items-center gap-2.5 rounded-xl border border-slate-200 bg-slate-50 px-3">
        <input
            :checked="Boolean(field.allowRootSelection)"
            type="checkbox"
            class="size-3.5 rounded border-slate-300"
            :disabled="disabled"
            @change="emit('update', { allowRootSelection: ($event.target as HTMLInputElement).checked })"
        />
        <span class="text-xs text-slate-700">Можно выбрать корень</span>
      </label>
    </div>

    <div class="space-y-1.5">
      <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">Поиск по умолчанию</label>
      <Input
          :model-value="field.defaultSearch"
          :disabled="disabled"
          placeholder="Значение для поиска при загрузке"
          class="h-8 text-sm"
          @update:model-value="emit('update', { defaultSearch: String($event) })"
      />
    </div>

    <!-- DepDrop section -->
    <div class="space-y-2 rounded-xl border border-slate-200 bg-slate-50 p-3">
      <div class="flex items-center justify-between">
        <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Зависимость от поля</span>
        <label class="flex cursor-pointer items-center gap-2">
          <input
              :checked="depDropEnabled"
              type="checkbox"
              class="size-3.5 rounded border-slate-300"
              :disabled="disabled"
              @change="depDropEnabled ? disableDepDrop() : enableDepDrop()"
          />
          <span class="text-[11px] text-slate-600">Включить</span>
        </label>
      </div>

      <template v-if="depDropEnabled && field.depDrop">
        <!-- Source field picker -->
        <div class="space-y-1">
          <label class="block text-[10px] font-medium text-slate-500">Зависит от поля</label>
          <Select
              :model-value="field.depDrop.fieldVarName"
              :disabled="disabled || candidateFields.length === 0"
              @update:model-value="patchDepDrop({ fieldVarName: $event })"
          >
            <SelectTrigger class="w-full rounded-lg border-slate-200 text-xs" size="sm">
              <SelectValue :placeholder="candidateFields.length ? 'Выберите поле...' : 'Нет доступных полей'"/>
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                  v-for="f in candidateFields"
                  :key="f.id"
                  :value="f.varName"
              >
                {{ f.label || f.varName }}
                <span class="ml-1 text-slate-400">{{ f.varName }}</span>
              </SelectItem>
            </SelectContent>
          </Select>
          <p v-if="candidateFields.length === 0" class="text-[10px] text-slate-400">
            Добавьте другое поле «Справочник» в блок, чтобы использовать зависимость.
          </p>
        </div>

        <!-- Filter key (column in child directory) -->
        <div class="space-y-1">
          <label class="block text-[10px] font-medium text-slate-500">Колонка фильтра (в этом справочнике)</label>
          <Input
              :model-value="field.depDrop.filterKey"
              :disabled="disabled"
              placeholder="например: city_id или region"
              class="h-8 text-xs"
              @update:model-value="patchDepDrop({ filterKey: String($event) })"
          />
        </div>

        <!-- Value key (what to extract from parent selection) -->
        <div class="space-y-1">
          <label class="block text-[10px] font-medium text-slate-500">Брать значение из</label>
          <Select
              :model-value="field.depDrop.valueKey"
              :disabled="disabled"
              @update:model-value="patchDepDrop({ valueKey: $event })"
          >
            <SelectTrigger class="w-full rounded-lg border-slate-200 text-xs" size="sm">
              <SelectValue/>
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="external_key">external_key — внешний ключ</SelectItem>
              <SelectItem value="id">id — идентификатор</SelectItem>
            </SelectContent>
          </Select>
          <p class="text-[10px] text-slate-400">
            Или введите ключ колонки из родительского справочника вручную:
          </p>
          <Input
              :model-value="!['external_key', 'id'].includes(field.depDrop.valueKey) ? field.depDrop.valueKey : ''"
              :disabled="disabled"
              placeholder="например: code или name"
              class="h-8 text-xs"
              @update:model-value="$event ? patchDepDrop({ valueKey: String($event) }) : patchDepDrop({ valueKey: 'external_key' })"
          />
        </div>
      </template>
    </div>
  </div>

  <DirectoryPickerDialog
      :open="pickerOpen"
      :selected-id="field.directoryId || undefined"
      @update:open="pickerOpen = $event"
      @select="onDirectorySelect"
  />
</template>
