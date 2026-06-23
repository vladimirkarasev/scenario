<script setup lang="ts">
import {Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle} from '@/components/ui/dialog'
import {Trash2} from 'lucide-vue-next'
import type {BlockField} from '@/modules/scenario/lib/scenario-block-fields'

defineProps<{
  open: boolean
  field: BlockField | null
}>()

const emit = defineEmits<{
  'update:open': [boolean]
  confirm: []
}>()
</script>

<template>
  <Dialog :open="open" @update:open="emit('update:open', $event)">
    <DialogContent class="max-w-sm overflow-hidden p-0">
      <DialogHeader class="border-b border-slate-100 px-6 py-4">
        <DialogTitle class="text-[15px] text-slate-900">Удалить поле?</DialogTitle>
        <DialogDescription class="sr-only">Подтверждение удаления поля</DialogDescription>
      </DialogHeader>
      <div class="px-6 py-5">
        <p v-if="field" class="text-[13px] text-slate-600">
          Поле
          <span class="font-semibold text-slate-900">{{ field.label || field.name || 'без названия' }}</span>
          будет удалено без возможности восстановления.
        </p>
      </div>
      <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-6 py-4">
        <button
            type="button"
            class="inline-flex h-8 items-center rounded-lg px-3 text-[12px] font-medium text-slate-600 ring-1 ring-slate-200 transition hover:bg-slate-50"
            @click="emit('update:open', false)"
        >
          Отмена
        </button>
        <button
            type="button"
            class="inline-flex h-8 items-center gap-1.5 rounded-lg bg-red-500 px-3 text-[12px] font-semibold text-white transition hover:bg-red-600"
            @click="emit('confirm')"
        >
          <Trash2 class="size-3.5"/>
          Удалить
        </button>
      </div>
    </DialogContent>
  </Dialog>
</template>
