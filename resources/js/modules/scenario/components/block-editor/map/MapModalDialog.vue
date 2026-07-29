<script setup lang="ts">
import {Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle} from '@/components/ui/dialog'
import {MapPin} from 'lucide-vue-next'

defineProps<{
  open: boolean
  title: string
}>()

defineEmits<{
  'update:open': [boolean]
}>()

defineOptions({inheritAttrs: false})
</script>

<template>
  <Dialog :open="open" @update:open="$emit('update:open', $event)">
    <DialogContent class="flex flex-col !max-w-6xl h-[90vh] overflow-hidden p-0">
      <DialogHeader class="shrink-0 border-b border-slate-100 px-6 py-4">
        <DialogTitle class="flex items-center gap-2.5">
          <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-slate-100">
            <MapPin class="size-3.5 text-slate-600"/>
          </div>
          <span class="text-[15px] font-semibold text-slate-900">{{ title }}</span>
        </DialogTitle>
        <DialogDescription class="sr-only">{{ title }}</DialogDescription>
      </DialogHeader>

      <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
        <slot/>
      </div>

      <div v-if="$slots.footer" class="flex shrink-0 items-center justify-end gap-2 border-t border-slate-100 px-6 py-3">
        <slot name="footer"/>
      </div>
    </DialogContent>
  </Dialog>
</template>
