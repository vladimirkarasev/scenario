<script lang="ts">
import {markRaw} from 'vue'
import {Lightbulb} from 'lucide-vue-next'

export const fieldMeta = {type: 'suggest', label: 'Подсказки', icon: markRaw(Lightbulb)}
</script>

<script setup lang="ts">
import {onMounted} from 'vue'
import {Select, SelectContent, SelectItem, SelectTrigger, SelectValue} from '@/components/ui/select'
import {Input} from '@/components/ui/input'
import {useSuggestProxyPicker} from '@/modules/scenario/composables/useSuggestProxyPicker'
import type {SuggestBlockField} from '../../../lib/scenario-block-fields'

const props = defineProps<{ field: SuggestBlockField; disabled?: boolean }>()
const emit = defineEmits<{ update: [patch: Partial<SuggestBlockField>] }>()
defineOptions({inheritAttrs: false})

const {proxies, loading, load} = useSuggestProxyPicker(() => props.field.proxyUuid)

onMounted(load)
</script>

<template>
  <div class="space-y-3">
    <!-- Proxy picker -->
    <div class="space-y-1.5">
      <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">Источник (proxy)</label>
      <Select
          :model-value="field.proxyUuid"
          :disabled="disabled || loading"
          @update:model-value="emit('update', { proxyUuid: String($event) })"
      >
        <SelectTrigger class="w-full rounded-xl border-slate-200 text-xs" size="sm">
          <SelectValue :placeholder="loading ? 'Загрузка...' : '— не выбрано —'"/>
        </SelectTrigger>
        <SelectContent>
          <SelectItem v-for="p in proxies" :key="p.uuid" :value="p.uuid">
            {{ p.name }}
          </SelectItem>
        </SelectContent>
      </Select>
      <p v-if="!loading && proxies.length === 0" class="text-[11px] text-slate-400">
        Нет proxy типа «Подсказки». Создайте эндпоинт с type=suggest.
      </p>
    </div>

    <!-- Label field -->
    <div class="space-y-1.5">
      <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">Поле-подпись</label>
      <Input
          :model-value="field.labelField"
          :disabled="disabled"
          placeholder="Ключ объекта для подписи (пусто = первый)"
          class="h-8 text-sm"
          @update:model-value="emit('update', { labelField: String($event) })"
      />
    </div>

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
  </div>
</template>
