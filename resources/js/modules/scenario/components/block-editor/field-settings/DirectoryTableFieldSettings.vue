<script lang="ts">
import {markRaw} from 'vue'
import {Table2} from 'lucide-vue-next'

export const fieldMeta = {type: 'directory_table', label: 'Таблица', icon: markRaw(Table2)}
</script>

<script setup lang="ts">
import {computed, ref, watch} from 'vue'
import {Select, SelectContent, SelectItem, SelectTrigger, SelectValue} from '@/components/ui/select'
import {Input} from '@/components/ui/input'
import {copyText} from '@/lib/clipboard'
import {ChevronsUpDown, Copy, Check, X} from 'lucide-vue-next'
import DirectoryPickerDialog from '@/modules/directories/components/DirectoryPickerDialog.vue'
import DirectoryLabelTemplateField from '@/modules/directories/components/DirectoryLabelTemplateField.vue'
import FilterCellEditor from './FilterCellEditor.vue'
import {useDirectorySchemaLoader} from '@/modules/directories/composables/useDirectorySchemaLoader'
import {useDirectoryItems} from '@/modules/directories/composables/useDirectoryItems'
import type {Directory} from '@/modules/directories/types/directory'
import type {DirectoryTableBlockField, DirectoryTableFieldConfig} from '../../../lib/scenario-block-fields'

const props = defineProps<{ field: DirectoryTableBlockField; disabled?: boolean }>()
const emit = defineEmits<{ update: [patch: Partial<DirectoryTableBlockField>] }>()
defineOptions({inheritAttrs: false})

const pickerOpen = ref(false)

const {directoryName, versions, schemaFields, versionLabel} = useDirectorySchemaLoader(
    computed(() => props.field.directoryId),
    computed(() => props.field.versionId),
    (id) => emit('update', {versionId: id}),
)

const fieldConfigs = computed(() =>
    schemaFields.value.map((df) => {
      const saved = (props.field.fields ?? []).find((c) => c.key === df.key)
      return {
        key: df.key,
        name: df.name,
        visible: saved ? saved.visible : true,
        defaultValue: saved ? saved.defaultValue : '',
        filterable: saved ? saved.filterable : false,
        lockFilter: saved ? saved.lockFilter : false,
        filterMode: saved?.filterMode ?? 'literal',
        filterValues: saved?.filterValues ?? [],
      }
    }),
)

const filterableConfigs = computed(() =>
    fieldConfigs.value.filter((c) => c.filterable && schemaFieldByKey(c.key)?.filterable),
)

const itemsCtx = useDirectoryItems(computed(() => props.field.directoryId).value)
const itemsLoaded = ref(false)

watch(
    [() => props.field.directoryId, () => props.field.versionId, filterableConfigs],
    async ([dirId]) => {
      if (!dirId || filterableConfigs.value.length === 0) return
      if (itemsLoaded.value) return
      const vId = props.field.versionId ? Number(props.field.versionId) : undefined
      await itemsCtx.loadItems(vId)
      itemsLoaded.value = true
    },
    {immediate: true},
)

function schemaFieldByKey(key: string) {
  return schemaFields.value.find((f) => f.key === key)
}

function listOptionsForKey(key: string): string[] {
  const f = schemaFieldByKey(key)
  return f ? itemsCtx.listOptions(f) : []
}

function onVersionChange(versionId: string): void {
  emit('update', {versionId, fields: [], labelTemplate: ''})
}

function onDirectorySelect(directory: Directory): void {
  directoryName.value = directory.name
  const activeId = directory.active_version ? String(directory.active_version.id) : ''
  emit('update', {directoryId: directory.id, versionId: activeId, fields: [], labelTemplate: ''})
}

function clearDirectory(): void {
  emit('update', {directoryId: '', versionId: '', fields: [], labelTemplate: ''})
}

function updateFieldConfig(key: string, patch: Partial<DirectoryTableFieldConfig>): void {
  const next = fieldConfigs.value.map((c) => c.key === key ? {...c, ...patch} : c)
  emit('update', {fields: next.map(({name: _n, ...rest}) => rest as DirectoryTableFieldConfig)})
}

const copiedKey = ref<string | null>(null)

async function copyVar(key: string): Promise<void> {
  const varName = props.field.varName
  if (!varName) return
  if (!await copyText(`{{ ${varName}.${key} }}`)) return
  copiedKey.value = key
  setTimeout(() => {
    copiedKey.value = null
  }, 1500)
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

    <!-- Field configs table -->
    <div v-if="field.directoryId && fieldConfigs.length" class="rounded-xl border border-slate-200">
      <div
          class="grid grid-cols-[1fr_1fr_auto_minmax(10rem,auto)_auto_auto] items-center rounded-t-xl border-b border-slate-100 bg-slate-50 px-3 py-1.5 text-[10px] font-bold uppercase tracking-wide text-slate-400">
        <span>Поле</span>
        <span>Переменная</span>
        <span class="w-16 text-center">Показывать</span>
        <span class="w-40 text-center" title="Стартовое значение фильтра. Для списков — выбор как в самом справочнике">По умолч.</span>
        <span class="w-12 text-center" title="Показывать фильтр-чип юзеру">Фильтр</span>
        <span class="w-12 text-center"
              title="Жёстко закрепить значение фильтра — юзер не сможет его изменить">Закрепить</span>
      </div>
      <div
          v-for="cfg in fieldConfigs"
          :key="cfg.key"
          class="grid grid-cols-[1fr_1fr_auto_minmax(10rem,auto)_auto_auto] items-center border-b border-slate-100 px-3 py-2 last:border-0"
      >
        <span class="text-[13px] text-slate-700">{{ cfg.name }}</span>

        <button
            v-if="field.varName"
            type="button"
            class="group flex min-w-0 items-center gap-1 rounded-md px-1.5 py-1 text-left transition hover:bg-slate-100"
            @click="copyVar(cfg.key)"
        >
          <code class="truncate font-mono text-[11px] text-slate-500">{{ field.varName }}.{{ cfg.key }}</code>
          <Check v-if="copiedKey === cfg.key" class="size-3 shrink-0 text-emerald-500"/>
          <Copy v-else class="size-3 shrink-0 text-slate-300 group-hover:text-slate-400"/>
        </button>
        <span v-else class="text-[11px] text-slate-300">—</span>

        <div class="flex w-16 justify-center">
          <input
              type="checkbox"
              :checked="cfg.visible"
              :disabled="disabled"
              class="size-3.5 rounded border-slate-300 accent-blue-600"
              @change="updateFieldConfig(cfg.key, { visible: ($event.target as HTMLInputElement).checked })"
          />
        </div>

        <div class="w-40 px-1">
          <FilterCellEditor
              v-if="schemaFieldByKey(cfg.key)"
              :schema-field="schemaFieldByKey(cfg.key)!"
              :cfg="cfg"
              :options="listOptionsForKey(cfg.key)"
              :options-loaded="itemsLoaded"
              :disabled="disabled"
              @update="(patch) => updateFieldConfig(cfg.key, patch)"
          />
        </div>

        <div class="flex w-12 justify-center">
          <input
              type="checkbox"
              :checked="cfg.filterable && Boolean(schemaFieldByKey(cfg.key)?.filterable)"
              :disabled="disabled || !schemaFieldByKey(cfg.key)?.filterable"
              :title="!schemaFieldByKey(cfg.key)?.filterable ? 'У этой колонки фильтр отключён в справочнике' : ''"
              class="size-3.5 rounded border-slate-300 accent-blue-600 disabled:opacity-40"
              @change="updateFieldConfig(cfg.key, { filterable: ($event.target as HTMLInputElement).checked })"
          />
        </div>

        <div class="flex w-12 justify-center">
          <input
              type="checkbox"
              :checked="cfg.lockFilter"
              :disabled="disabled || !(cfg.defaultValue || (cfg.filterValues ?? []).length)"
              title="Закрепить фильтр — юзер не сможет его убрать"
              class="size-3.5 rounded border-slate-300 accent-blue-600 disabled:opacity-40"
              @change="updateFieldConfig(cfg.key, { lockFilter: ($event.target as HTMLInputElement).checked })"
          />
        </div>
      </div>
    </div>

    <!-- Label template (only when selection is enabled) -->
    <DirectoryLabelTemplateField
        v-if="field.directoryId && field.allowSelection"
        label="Шаблон отображения выбранного"
        :model-value="field.labelTemplate"
        :fields="schemaFields"
        :disabled="disabled"
        @update:model-value="emit('update', { labelTemplate: $event })"
    />

    <label class="flex h-9 items-center gap-2.5 rounded-xl border border-slate-200 bg-slate-50 px-3">
      <input
          :checked="Boolean(field.allowSelection)"
          type="checkbox"
          class="size-3.5 rounded border-slate-300 accent-blue-600"
          :disabled="disabled"
          @change="emit('update', { allowSelection: ($event.target as HTMLInputElement).checked, multiple: ($event.target as HTMLInputElement).checked ? field.multiple : false })"
      />
      <span class="text-xs text-slate-700">Разрешить выбор строк</span>
    </label>
    <label
        class="flex h-9 items-center gap-2.5 rounded-xl border border-slate-200 bg-slate-50 px-3 transition"
        :class="!field.allowSelection ? 'pointer-events-none opacity-40' : ''"
    >
      <input
          :checked="Boolean(field.multiple)"
          type="checkbox"
          class="size-3.5 rounded border-slate-300 accent-blue-600"
          :disabled="disabled || !field.allowSelection"
          @change="emit('update', { multiple: ($event.target as HTMLInputElement).checked })"
      />
      <span class="text-xs text-slate-700">Мультивыбор строк</span>
    </label>

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
  </div>

  <DirectoryPickerDialog
      :open="pickerOpen"
      :selected-id="field.directoryId || undefined"
      @update:open="pickerOpen = $event"
      @select="onDirectorySelect"
  />
</template>
