<script setup lang="ts">
import {Check, Plus, Trash2} from 'lucide-vue-next'
import FormField from './FormField.vue'
import FormJsonInput from './FormJsonInput.vue'
import {Input} from '@/components/ui/input'

interface MockVariant {
  name: string | null
  status: number
  body: Record<string, unknown> | unknown[]
  headers: Record<string, unknown> | null
  is_active: boolean
}

const props = withDefaults(defineProps<{
  modelValue: MockVariant[]
  label?: string
  hint?: string
  error?: string
}>(), {})

const emit = defineEmits<{
  'update:modelValue': [value: MockVariant[]]
}>()

function emptyVariant(active: boolean): MockVariant {
  return {name: null, status: 200, body: {}, headers: null, is_active: active}
}

function add(): void {
  emit('update:modelValue', [...props.modelValue, emptyVariant(props.modelValue.length === 0)])
}

function remove(idx: number): void {
  const next = props.modelValue.filter((_, i) => i !== idx)
  if (next.length && !next.some(m => m.is_active)) next[0] = {...next[0], is_active: true}
  emit('update:modelValue', next)
}

function update(idx: number, patch: Partial<MockVariant>): void {
  emit('update:modelValue', props.modelValue.map((m, i) => i === idx ? {...m, ...patch} : m))
}

function setActive(idx: number): void {
  emit('update:modelValue', props.modelValue.map((m, i) => ({...m, is_active: i === idx})))
}
</script>

<template>
  <FormField :label="label" :hint="hint" :error="error">
    <div class="space-y-3">
      <div
          v-for="(mock, mi) in modelValue"
          :key="mi"
          class="rounded-xl border bg-slate-50/50 p-3"
          :class="mock.is_active ? 'border-amber-300 ring-1 ring-amber-200' : 'border-slate-200'"
      >
        <label class="mb-2.5 flex cursor-pointer items-center gap-2">
          <span
              class="flex size-4 flex-none items-center justify-center rounded border transition"
              :class="mock.is_active ? 'border-amber-500 bg-amber-500' : 'border-slate-300 bg-white'"
          >
            <Check v-if="mock.is_active" class="size-2.5 text-white"/>
          </span>
          <input type="checkbox" class="sr-only" :checked="mock.is_active" @change="setActive(mi)"/>
          <span class="text-[12px] font-medium" :class="mock.is_active ? 'text-amber-700' : 'text-slate-500'">
            {{ mock.is_active ? 'Показывается сейчас' : 'Показывать этот ответ' }}
          </span>
        </label>

        <div class="mb-2.5 flex items-center gap-2">
          <Input
              :model-value="mock.name ?? ''"
              placeholder="Название варианта"
              class="flex-1"
              @update:model-value="(v: string | number) => update(mi, { name: (v ? String(v) : null) })"
          />
          <Input
              :model-value="mock.status"
              type="number"
              min="100"
              max="599"
              class="w-20 text-center font-mono"
              @update:model-value="(v: string | number) => update(mi, { status: Number(v) })"
          />
          <button
              type="button"
              class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-red-50 hover:text-red-600"
              title="Удалить вариант"
              @click="remove(mi)"
          >
            <Trash2 :size="13"/>
          </button>
        </div>

        <FormJsonInput
            :model-value="mock.headers ?? {}"
            label="Headers"
            :rows="3"
            @update:model-value="(v) => update(mi, { headers: !Array.isArray(v) && Object.keys(v).length ? v : null })"
        />
        <div class="mt-2">
          <FormJsonInput
              :model-value="mock.body"
              label="Body"
              :rows="4"
              @update:model-value="(v) => update(mi, { body: v })"
          />
        </div>
      </div>

      <button
          type="button"
          class="inline-flex h-9 w-full items-center justify-center gap-1.5 rounded-xl border border-dashed border-slate-300 text-[12px] font-medium text-slate-500 transition hover:border-amber-300 hover:bg-amber-50 hover:text-amber-700"
          @click="add"
      >
        <Plus :size="14"/>
        Добавить вариант
      </button>

      <p v-if="!modelValue.length" class="text-[12px] text-slate-400">
        Без вариантов мок-ответ работать не будет — handler выполнится как обычно.
      </p>
    </div>
  </FormField>
</template>
