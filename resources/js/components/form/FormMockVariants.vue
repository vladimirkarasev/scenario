<script setup lang="ts">
import {Plus, Trash2} from 'lucide-vue-next'
import FormField from './FormField.vue'
import FormJsonInput from './FormJsonInput.vue'
import {Input} from '@/components/ui/input'

interface MockVariant {
  name: string | null
  status: number
  body: Record<string, unknown> | unknown[]
  headers: Record<string, unknown> | null
  match: Record<string, unknown> | null
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

function emptyVariant(): MockVariant {
  return {name: null, status: 200, body: {}, headers: null, match: null}
}

function add(): void {
  emit('update:modelValue', [...props.modelValue, emptyVariant()])
}

function remove(idx: number): void {
  emit('update:modelValue', props.modelValue.filter((_, i) => i !== idx))
}

function update(idx: number, patch: Partial<MockVariant>): void {
  emit('update:modelValue', props.modelValue.map((m, i) => i === idx ? {...m, ...patch} : m))
}
</script>

<template>
  <FormField :label="label" :hint="hint" :error="error">
    <div class="space-y-3">
      <div
          v-for="(mock, mi) in modelValue"
          :key="mi"
          class="rounded-xl border border-slate-200 bg-slate-50/50 p-3"
      >
        <div class="mb-2.5 flex items-center gap-2">
          <span
              class="inline-flex h-5 items-center rounded-full bg-slate-200 px-2 text-[11px] font-semibold text-slate-600">#{{
              mi + 1
            }}</span>
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

        <div class="grid gap-2 sm:grid-cols-2">
          <FormJsonInput
              :model-value="mock.match ?? {}"
              label="Условие match"
              :rows="3"
              @update:model-value="(v) => update(mi, { match: !Array.isArray(v) && Object.keys(v).length ? v : null })"
          />
          <FormJsonInput
              :model-value="mock.headers ?? {}"
              label="Headers"
              :rows="3"
              @update:model-value="(v) => update(mi, { headers: !Array.isArray(v) && Object.keys(v).length ? v : null })"
          />
        </div>
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
