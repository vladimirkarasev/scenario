<script setup lang="ts">
import {
  Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog'
import {Button} from '@/components/ui/button'
import {RefreshCw, Trash2} from 'lucide-vue-next'

withDefaults(defineProps<{
  open: boolean
  title: string
  description?: string
  confirmLabel?: string
  cancelLabel?: string
  loading?: boolean
  error?: string | null
  variant?: 'destructive' | 'default'
}>(), {
  description: '',
  confirmLabel: 'Удалить',
  cancelLabel: 'Отмена',
  loading: false,
  error: null,
  variant: 'destructive',
})

const emit = defineEmits<{
  'update:open': [v: boolean]
  confirm: []
}>()
</script>

<template>
  <Dialog :open="open" @update:open="emit('update:open', $event)">
    <DialogContent class="sm:max-w-sm">
      <DialogHeader>
        <DialogTitle>{{ title }}</DialogTitle>
      </DialogHeader>
      <p v-if="description" class="text-[13px] text-slate-500">
        <slot>{{ description }}</slot>
      </p>
      <p v-else class="text-[13px] text-slate-500">
        <slot/>
      </p>
      <div v-if="error" class="rounded-xl bg-red-50 px-3 py-2 text-[12px] text-red-600">{{ error }}</div>
      <DialogFooter>
        <Button variant="outline" @click="emit('update:open', false)">{{ cancelLabel }}</Button>
        <Button :variant="variant" :disabled="loading" class="gap-1.5" @click="emit('confirm')">
          <RefreshCw v-if="loading" :size="13" class="animate-spin"/>
          <Trash2 v-else-if="variant === 'destructive'" :size="13"/>
          {{ confirmLabel }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
