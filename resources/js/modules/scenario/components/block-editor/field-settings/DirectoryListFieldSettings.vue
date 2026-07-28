<script lang="ts">
import {markRaw} from 'vue'
import {List} from 'lucide-vue-next'

export const fieldMeta = {type: 'directory_list', label: 'Список', icon: markRaw(List)}
</script>

<script setup lang="ts">
import {computed, ref, watch} from 'vue'
import {Select, SelectContent, SelectItem, SelectTrigger, SelectValue} from '@/components/ui/select'
import {Input} from '@/components/ui/input'
import {ChevronsUpDown, Copy, Check, X} from 'lucide-vue-next'
import DirectoryPickerDialog from '@/modules/directories/components/DirectoryPickerDialog.vue'
import DirectoryLabelTemplateField from '@/modules/directories/components/DirectoryLabelTemplateField.vue'
import FilterCellEditor from './FilterCellEditor.vue'
import {useDirectorySchemaLoader} from '@/modules/directories/composables/useDirectorySchemaLoader'
import {useDirectoryItems} from '@/modules/directories/composables/useDirectoryItems'
import type {Directory} from '@/modules/directories/types/directory'
import type {DirectoryListBlockField, DirectoryListDepDrop, DirectoryTableFieldConfig} from '../../../lib/scenario-block-fields'

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

function updateFieldConfig(key: string, patch: Partial<DirectoryTableFieldConfig>): void {
  const next = fieldConfigs.value.map((c) => c.key === key ? {...c, ...patch} : c)
  emit('update', {fields: next.map(({name: _n, ...rest}) => rest as DirectoryTableFieldConfig)})
}

const copiedKey = ref<string | null>(null)

async function copyVar(key: string): Promise<void> {
  const varName = props.field.varName
  if (!varName) return
  await navigator.clipboard.writeText(`{{ ${varName}.${key} }}`)
  copiedKey.value = key
  setTimeout(() => {
    copiedKey.value = null
  }, 1500)
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

    <!-- Field configs table -->
    <div v-if="field.directoryId && fieldConfigs.length" class="rounded-xl border border-slate-200">
      <div
          class="grid grid-cols-[1fr_1fr_minmax(10rem,auto)_auto_auto] items-center rounded-t-xl border-b border-slate-100 bg-slate-50 px-3 py-1.5 text-[10px] font-bold uppercase tracking-wide text-slate-400">
        <span>Поле</span>
        <span>Переменная</span>
        <span class="w-40 text-center" title="Стартовое значение фильтра. Для списков — выбор как в самом справочнике">По умолч.</span>
        <span class="w-12 text-center" title="Показывать фильтр-чип юзеру">Фильтр</span>
        <span class="w-12 text-center"
              title="Жёстко закрепить значение фильтра — юзер не сможет его изменить">Закрепить</span>
      </div>
      <div
          v-for="cfg in fieldConfigs"
          :key="cfg.key"
          class="grid grid-cols-[1fr_1fr_minmax(10rem,auto)_auto_auto] items-center border-b border-slate-100 px-3 py-2 last:border-0"
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
