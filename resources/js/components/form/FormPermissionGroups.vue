<script setup lang="ts">
import {computed} from 'vue'
import FormField from './FormField.vue'

interface Permission {
  name: string;
  title?: string | null
}

interface Group {
  title: string;
  perms: Permission[]
}

const props = withDefaults(defineProps<{
  modelValue: string[]
  groups: Group[]
  label?: string
  hint?: string
  error?: string
  required?: boolean
}>(), {
  required: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: string[]]
}>()

const allNames = computed(() => props.groups.flatMap(g => g.perms.map(p => p.name)))
const allSelected = computed(() => allNames.value.length > 0 && allNames.value.every(n => props.modelValue.includes(n)))

function toggle(name: string): void {
  const next = props.modelValue.includes(name)
      ? props.modelValue.filter(n => n !== name)
      : [...props.modelValue, name]
  emit('update:modelValue', next)
}

function groupSelected(group: Group): boolean {
  return group.perms.every(p => props.modelValue.includes(p.name))
}

function toggleGroup(group: Group): void {
  const names = group.perms.map(p => p.name)
  if (groupSelected(group)) {
    emit('update:modelValue', props.modelValue.filter(n => !names.includes(n)))
  } else {
    const merged = new Set([...props.modelValue, ...names])
    emit('update:modelValue', Array.from(merged))
  }
}

function toggleAll(): void {
  emit('update:modelValue', allSelected.value ? [] : allNames.value)
}
</script>

<template>
  <FormField :label="label" :hint="hint" :error="error" :required="required">
    <div class="space-y-3">
      <button
          type="button"
          class="text-[12px] font-medium text-blue-600 underline transition hover:text-blue-800"
          @click="toggleAll"
      >
        {{ allSelected ? 'Снять всё' : 'Выбрать всё' }}
      </button>
      <div class="space-y-3">
        <div v-for="g in groups" :key="g.title" class="rounded-xl border border-slate-200 bg-white p-3">
          <label class="mb-2 flex cursor-pointer items-center gap-2 text-[12px] font-semibold text-slate-800">
            <input
                type="checkbox"
                class="h-3.5 w-3.5 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                :checked="groupSelected(g)"
                @change="toggleGroup(g)"
            />
            {{ g.title }}
          </label>
          <div class="grid grid-cols-1 gap-1 pl-5 md:grid-cols-2">
            <label
                v-for="p in g.perms"
                :key="p.name"
                class="flex cursor-pointer items-center gap-2 text-[12px] text-slate-700 transition hover:text-slate-900"
            >
              <input
                  type="checkbox"
                  class="h-3.5 w-3.5 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                  :checked="modelValue.includes(p.name)"
                  @change="toggle(p.name)"
              />
              <span>{{ p.title || p.name }}</span>
            </label>
          </div>
        </div>
      </div>
    </div>
  </FormField>
</template>
