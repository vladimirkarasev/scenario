<script setup lang="ts">
import {computed, ref, watch} from 'vue'
import {Textarea} from '@/components/ui/textarea'
import FormField from './FormField.vue'
import {useFieldId} from './useFieldId'

const props = withDefaults(defineProps<{
  modelValue: Record<string, unknown> | unknown[] | null
  id?: string
  name?: string
  label?: string
  hint?: string
  error?: string
  required?: boolean
  rows?: number
  placeholder?: string
}>(), {
  required: false,
  rows: 6,
  placeholder: '{}',
})

const fieldId = useFieldId(() => props.id, () => props.name)

const emit = defineEmits<{
  'update:modelValue': [value: Record<string, unknown> | unknown[]]
  'parse-error': [message: string | null]
}>()

function stringify(v: Record<string, unknown> | unknown[] | null | undefined): string {
  return JSON.stringify(v ?? {}, null, 2)
}

const text = ref(stringify(props.modelValue))
const parseError = ref<string | null>(null)

const displayError = computed(() => props.error || parseError.value || '')

watch(() => props.modelValue, (next) => {
  try {
    const current = JSON.parse(text.value || '{}')
    if (JSON.stringify(current) === JSON.stringify(next)) return
  } catch { /* fall through and reset */
  }
  text.value = stringify(next)
})

function onInput(value: string): void {
  text.value = value
  try {
    const parsed = value.trim() === '' ? {} : JSON.parse(value)
    if (parsed === null || typeof parsed !== 'object') {
      throw new Error('Должен быть JSON-объект или массив')
    }
    parseError.value = null
    emit('parse-error', null)
    emit('update:modelValue', parsed as Record<string, unknown> | unknown[])
  } catch (e) {
    const msg = e instanceof Error ? e.message : 'Невалидный JSON'
    parseError.value = msg
    emit('parse-error', msg)
  }
}
</script>

<template>
  <FormField :label="label" :hint="hint" :error="displayError" :required="required" :for="fieldId">
        <Textarea
            :id="fieldId"
            :name="name || fieldId"
            :model-value="text"
            :rows="rows"
            :placeholder="placeholder"
            autocomplete="off"
            spellcheck="false"
            class="font-mono text-[12px]"
            :aria-invalid="!!displayError || undefined"
            @update:model-value="onInput(String($event ?? ''))"
        />
  </FormField>
</template>
