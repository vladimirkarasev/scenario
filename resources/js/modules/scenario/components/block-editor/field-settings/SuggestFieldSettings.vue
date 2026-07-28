<script lang="ts">
import {markRaw} from 'vue'
import {Lightbulb} from 'lucide-vue-next'

export const fieldMeta = {type: 'suggest', label: 'Подсказки', icon: markRaw(Lightbulb)}
</script>

<script setup lang="ts">
import {computed, onMounted, ref} from 'vue'
import {Check, ChevronsUpDown, Copy, X} from 'lucide-vue-next'
import {Input} from '@/components/ui/input'
import SuggestProxyPickerDialog from '@/modules/scenario/components/pickers/SuggestProxyPickerDialog.vue'
import DirectoryLabelTemplateField from '@/modules/directories/components/DirectoryLabelTemplateField.vue'
import {useSuggestProxyPicker} from '@/modules/scenario/composables/useSuggestProxyPicker'
import {useSuggestResultFields} from '@/modules/scenario/composables/useSuggestResultFields'
import type {WebhookEndpoint} from '@/modules/proxy/types/webhook'
import type {SuggestBlockField, SuggestFieldConfig} from '../../../lib/scenario-block-fields'

const props = defineProps<{ field: SuggestBlockField; disabled?: boolean }>()
const emit = defineEmits<{ update: [patch: Partial<SuggestBlockField>] }>()
defineOptions({inheritAttrs: false})

const pickerOpen = ref(false)

const {load: loadProxies, selected} = useSuggestProxyPicker(() => props.field.proxyUuid)
const proxyName = computed(() => selected.value?.name ?? '')

onMounted(loadProxies)

const {fields: resultFields, loading} = useSuggestResultFields(computed(() => props.field.proxyUuid))

const fieldConfigs = computed(() =>
    resultFields.value.map((rf) => {
      const saved = (props.field.fields ?? []).find((c) => c.key === rf.key)
      return {
        key: rf.key,
        label: rf.label,
        filterable: Boolean(rf.filterable),
        filterKey: rf.filter_key ?? rf.key,
        values: rf.values ?? [],
        defaultValue: saved ? saved.defaultValue : '',
      }
    }),
)

function updateFieldConfig(key: string, patch: Partial<Pick<SuggestFieldConfig, 'defaultValue'>>): void {
  const next = fieldConfigs.value.map((c) => c.key === key ? {...c, ...patch} : c)
  emit('update', {
    fields: next.map(({label: _l, filterable: _f, values: _v, ...rest}) => rest as SuggestFieldConfig),
  })
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

function onProxySelect(proxy: WebhookEndpoint): void {
  emit('update', {proxyUuid: proxy.uuid, fields: [], labelTemplate: ''})
}

function clearProxy(): void {
  emit('update', {proxyUuid: '', fields: [], labelTemplate: ''})
}
</script>

<template>
  <div class="space-y-3">
    <!-- Integration picker -->
    <div class="space-y-1.5">
      <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">Интеграция</label>
      <div class="flex items-center gap-1.5">
        <button
            type="button"
            class="flex h-8 flex-1 items-center justify-between gap-2 rounded-lg border border-slate-200 bg-white px-2.5 text-xs transition hover:border-slate-300 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="disabled"
            @click="pickerOpen = true"
        >
                    <span :class="field.proxyUuid ? 'text-slate-800' : 'text-slate-400'">
                        {{ field.proxyUuid ? (proxyName || 'Загрузка...') : '— не выбрано —' }}
                    </span>
          <ChevronsUpDown class="size-3 shrink-0 text-slate-400"/>
        </button>
        <button
            v-if="field.proxyUuid && !disabled"
            type="button"
            class="flex size-8 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-400 transition hover:border-slate-300 hover:text-slate-600"
            @click="clearProxy"
        >
          <X class="size-3.5"/>
        </button>
      </div>
    </div>

    <!-- Result fields -->
    <div v-if="field.proxyUuid && fieldConfigs.length" class="rounded-xl border border-slate-200">
      <div
          class="grid grid-cols-[1fr_1fr_minmax(9rem,auto)] items-center rounded-t-xl border-b border-slate-100 bg-slate-50 px-3 py-1.5 text-[10px] font-bold uppercase tracking-wide text-slate-400">
        <span>Поле результата</span>
        <span>Переменная</span>
        <span class="w-36 text-center" title="Значение уходит параметром запроса, чтобы сузить подсказки. Доступно только для полей, поддерживающих фильтрацию">По умолч. (фильтр)</span>
      </div>
      <div
          v-for="cfg in fieldConfigs"
          :key="cfg.key"
          class="grid grid-cols-[1fr_1fr_minmax(9rem,auto)] items-center border-b border-slate-100 px-3 py-2 last:border-0"
      >
        <div class="min-w-0">
          <div class="text-[13px] text-slate-700">{{ cfg.label }}</div>
          <code class="truncate font-mono text-[11px] text-slate-400">{{ cfg.key }}</code>
        </div>

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

        <div class="w-36 px-1">
          <select
              v-if="cfg.values.length"
              :value="cfg.defaultValue"
              :disabled="disabled || !cfg.filterable"
              :title="!cfg.filterable ? 'Интеграция не поддерживает фильтрацию по этому полю' : ''"
              class="h-8 w-full rounded-lg border border-input bg-white px-2 text-sm disabled:cursor-not-allowed disabled:opacity-50"
              @change="updateFieldConfig(cfg.key, { defaultValue: ($event.target as HTMLSelectElement).value })"
          >
            <option value="">—</option>
            <option v-for="val in cfg.values" :key="val" :value="val">{{ val }}</option>
          </select>
          <Input
              v-else
              :model-value="cfg.defaultValue"
              :disabled="disabled || !cfg.filterable"
              :title="!cfg.filterable ? 'Интеграция не поддерживает фильтрацию по этому полю' : ''"
              class="h-8 text-sm"
              @update:model-value="updateFieldConfig(cfg.key, { defaultValue: String($event) })"
          />
        </div>
      </div>
    </div>
    <p v-else-if="field.proxyUuid && !loading" class="text-[11px] text-slate-400">
      У этой интеграции нет описанных полей результата.
    </p>

    <!-- Label template -->
    <DirectoryLabelTemplateField
        v-if="field.proxyUuid"
        label="Шаблон отображения подсказки"
        :model-value="field.labelTemplate"
        :fields="resultFields"
        :disabled="disabled"
        @update:model-value="emit('update', { labelTemplate: $event })"
    />

    <!-- Placeholder -->
    <div class="space-y-1.5">
      <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">Подсказка ввода</label>
      <Input
          :model-value="field.placeholder"
          :disabled="disabled"
          placeholder="Например: начните вводить адрес"
          class="h-8 text-sm"
          @update:model-value="emit('update', { placeholder: String($event) })"
      />
    </div>

    <!-- Count -->
    <div class="space-y-1.5">
      <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">Количество подсказок</label>
      <Input
          type="number"
          min="1"
          max="20"
          :model-value="field.count"
          :disabled="disabled"
          class="h-8 text-sm"
          @update:model-value="emit('update', { count: Math.min(20, Math.max(1, Math.round(Number($event) || 5))) })"
      />
    </div>
  </div>

  <SuggestProxyPickerDialog
      :open="pickerOpen"
      :selected-uuid="field.proxyUuid || undefined"
      @update:open="pickerOpen = $event"
      @select="onProxySelect"
  />
</template>
