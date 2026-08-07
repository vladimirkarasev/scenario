<script setup lang="ts">
import {ref, watch} from 'vue'
import {Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle} from '@/components/ui/dialog'
import {Button} from '@/components/ui/button'
import {FormError, FormInput, FormSelect} from '@/components/form'
import {useZodForm} from '@/composables/useZodForm'
import {fieldPresetSchema} from '@/modules/scenario/schemas/fieldPresetSchema'
import type {BlockField} from '@/modules/scenario/lib/scenario-block-fields'
import type {ScenarioFieldPreset} from '@/modules/scenario/types/field-preset'

const props = defineProps<{
  open: boolean
  preset: ScenarioFieldPreset | null
  field: BlockField | null
  presets: ScenarioFieldPreset[]
  allowPresetSelection?: boolean
  saving?: boolean
  externalError?: string | null
}>()

const emit = defineEmits<{
  'update:open': [boolean]
  save: [{name: string; field: BlockField; preset: ScenarioFieldPreset | null}]
}>()

const {formData, errors, formError, submitting, submit, reset} = useZodForm(fieldPresetSchema, {name: ''})
const targetPresetId = ref('')

function selectedPreset(): ScenarioFieldPreset | null {
  if (!props.allowPresetSelection) return props.preset
  return props.presets.find((item) => item.id === targetPresetId.value) ?? null
}

watch(
    () => [props.open, props.preset] as const,
    ([open, preset]) => {
      if (!open) return
      targetPresetId.value = preset?.id ?? ''
      reset({name: preset?.name ?? props.field?.label ?? ''})
    },
    {immediate: true},
)

watch(targetPresetId, (id) => {
  const preset = props.presets.find((item) => item.id === id)
  if (preset) formData.name = preset.name
})

async function save(): Promise<void> {
  if (!props.field) return

  try {
    await submit(async ({name}) => {
      emit('save', {name, field: props.field!, preset: selectedPreset()})
    })
  } catch {
    return
  }
}
</script>

<template>
  <Dialog :open="open" @update:open="emit('update:open', $event)">
    <DialogContent class="sm:max-w-md">
      <DialogHeader>
        <DialogTitle>{{ preset ? 'Редактировать пользовательское поле' : 'Сохранить пользовательское поле' }}</DialogTitle>
        <DialogDescription>
          {{ preset
            ? 'Название и текущая конфигурация поля заменят сохранённый шаблон.'
            : 'Шаблон будет доступен только в текущем проекте.' }}
        </DialogDescription>
      </DialogHeader>

      <form class="space-y-4" @submit.prevent="save">
        <FormSelect
            v-if="allowPresetSelection && presets.length"
            v-model="targetPresetId"
            name="target_preset"
            label="Действие"
            hint="Выберите существующий шаблон, чтобы заменить его текущими настройками поля."
        >
          <option value="">Создать новый шаблон</option>
          <option v-for="item in presets" :key="item.id" :value="item.id">
            Обновить: {{ item.name }}
          </option>
        </FormSelect>
        <FormInput
            v-model="formData.name"
            name="name"
            label="Название"
            placeholder="Например, ФИО клиента"
            :error="errors.name"
            required
            autofocus
        />
        <FormError :message="formError || externalError" />

        <DialogFooter>
          <Button type="button" variant="outline" :disabled="submitting || saving" @click="emit('update:open', false)">
            Отмена
          </Button>
          <Button type="submit" :disabled="submitting || saving || !field">
            {{ submitting || saving ? 'Сохранение…' : (selectedPreset() ? 'Обновить' : 'Сохранить') }}
          </Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>
