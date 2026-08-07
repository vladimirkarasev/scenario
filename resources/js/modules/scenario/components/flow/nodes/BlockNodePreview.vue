<script setup lang="ts">
import {computed} from 'vue'
import {Check, ChevronDown, EyeOff} from 'lucide-vue-next'
import TiptapTextRenderer from '@/modules/scenario/components/tiptap/TiptapTextRenderer.vue'
import {buildBlockNodePreviewItems} from '@/modules/scenario/lib/block-node-preview'
import type {BlockField} from '@/modules/scenario/lib/scenario-block-fields'

const props = defineProps<{
  layoutDocument?: unknown
  fields: BlockField[]
}>()

const previewItems = computed(() => buildBlockNodePreviewItems(props.layoutDocument, props.fields))

function fieldTitle(field: BlockField): string {
  return field.label || field.name || field.type
}

function fieldPlaceholder(field: BlockField): string {
  if ('placeholder' in field && field.placeholder) {
    return field.placeholder
  }

  if (field.type === 'select' || field.type === 'directory_list') {
    return 'Выберите значение'
  }

  if (field.type === 'date' || field.type === 'datetime') {
    return 'Выберите дату'
  }

  return 'Введите значение'
}
</script>

<template>
  <div class="block-node-preview grid gap-2 text-left">
    <template v-for="item in previewItems" :key="item.key">
      <div v-if="item.type === 'text'" class="block-node-preview__text">
        <TiptapTextRenderer :document="item.document" />
      </div>

      <div
          v-else-if="item.field.type === 'checkbox'"
          class="flex items-center gap-2 rounded-md border border-slate-200 bg-white px-2 py-1.5"
      >
        <span class="flex size-3 shrink-0 items-center justify-center rounded-[3px] border border-slate-300 bg-slate-50">
          <Check v-if="item.field.checked" class="size-2 text-sky-600" />
        </span>
        <span class="truncate text-[9px] font-medium text-slate-600">{{ fieldTitle(item.field) }}</span>
      </div>

      <div
          v-else-if="item.field.type === 'hidden'"
          class="flex items-center gap-1.5 rounded-md border border-dashed border-slate-200 bg-slate-50/70 px-2 py-1.5 text-slate-400"
      >
        <EyeOff class="size-2.5 shrink-0" />
        <span class="truncate text-[9px]">{{ fieldTitle(item.field) }}</span>
      </div>

      <div v-else class="grid gap-1">
        <span class="truncate text-[9px] font-medium leading-none text-slate-500">
          {{ fieldTitle(item.field) }}
          <span v-if="item.field.required" class="text-rose-400">*</span>
        </span>
        <div
            class="flex min-h-6 items-center rounded-md border border-slate-200 bg-white px-2 text-[9px] text-slate-300"
            :class="item.field.type === 'textarea' ? 'h-9 items-start pt-1.5' : ''"
        >
          <span class="min-w-0 flex-1 truncate">{{ fieldPlaceholder(item.field) }}</span>
          <ChevronDown
              v-if="item.field.type === 'select' || item.field.type === 'directory_list'"
              class="size-2.5 shrink-0"
          />
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.block-node-preview__text :deep(.tiptap-content) {
  font-size: 10px;
  line-height: 1.35;
}

.block-node-preview__text :deep(.tiptap-content > :first-child) {
  margin-top: 0;
}

.block-node-preview__text :deep(.tiptap-content > :last-child) {
  margin-bottom: 0;
}

.block-node-preview__text :deep(.tiptap-content h1),
.block-node-preview__text :deep(.tiptap-content h2),
.block-node-preview__text :deep(.tiptap-content h3),
.block-node-preview__text :deep(.tiptap-content h4),
.block-node-preview__text :deep(.tiptap-content h5),
.block-node-preview__text :deep(.tiptap-content h6),
.block-node-preview__text :deep(.tiptap-content p),
.block-node-preview__text :deep(.tiptap-content ul),
.block-node-preview__text :deep(.tiptap-content ol),
.block-node-preview__text :deep(.tiptap-content blockquote),
.block-node-preview__text :deep(.tiptap-content pre),
.block-node-preview__text :deep(.tiptap-content table),
.block-node-preview__text :deep(.tiptap-content details) {
  margin-block: 0.25rem;
}

.block-node-preview__text :deep(.tiptap-content h1),
.block-node-preview__text :deep(.tiptap-content h2),
.block-node-preview__text :deep(.tiptap-content h3),
.block-node-preview__text :deep(.tiptap-content h4),
.block-node-preview__text :deep(.tiptap-content h5),
.block-node-preview__text :deep(.tiptap-content h6) {
  font-size: 11px;
  line-height: 1.3;
}

.block-node-preview__text :deep(.tiptap-content details),
.block-node-preview__text :deep(.tiptap-content table) {
  max-width: 100%;
  overflow: hidden;
}
</style>
