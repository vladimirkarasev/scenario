<script setup lang="ts">
import {Input} from '@/components/ui/input'
import {Label} from '@/components/ui/label'
import type {InputBlockField, EmailBlockField, PhoneBlockField, VinBlockField, GrzBlockField} from '../../../lib/scenario-block-fields'

type SimpleTextField = InputBlockField | EmailBlockField | PhoneBlockField | VinBlockField | GrzBlockField
type SimpleTextPatch = Partial<Pick<SimpleTextField, 'placeholder' | 'value'>>

defineProps<{ field: SimpleTextField; disabled?: boolean }>()
const emit = defineEmits<{ update: [patch: SimpleTextPatch] }>()
defineOptions({inheritAttrs: false})
</script>

<template>
  <div class="space-y-1.5">
    <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Placeholder</Label>
    <Input :model-value="field.placeholder" class="h-8 text-sm" :disabled="disabled"
           @update:model-value="emit('update', { placeholder: $event })" />
  </div>
  <div class="space-y-1.5">
    <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">По умолчанию</Label>
    <Input :model-value="field.value" class="h-8 text-sm" :disabled="disabled"
           @update:model-value="emit('update', { value: $event })" />
  </div>
</template>
