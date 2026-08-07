<script setup lang="ts">
import {computed} from 'vue'
import {Eye} from 'lucide-vue-next'
import BlockRenderer from '@/modules/scenario/components/player/BlockRenderer.vue'
import {blockFieldsToSurveyBlocks} from '@/modules/scenario/lib/block-field-to-survey-block'
import type {BlockField} from '@/modules/scenario/lib/scenario-block-fields'

const props = defineProps<{
  title: string
  hideTitle?: boolean
  fields: BlockField[]
  layoutDocument?: unknown
  context?: Record<string, unknown>
}>()

const visibleBlocks = computed(() => blockFieldsToSurveyBlocks(props.fields))
</script>

<template>
  <div class="mx-auto max-w-2xl px-6 py-6">
    <BlockRenderer
        :title="title || 'Шаг'"
        :hide-title="hideTitle"
        :blocks="visibleBlocks"
        :layout-document="layoutDocument"
        :context="context ?? {}"
    />

    <div class="mt-3 flex items-center justify-center gap-1.5 text-[11px] text-slate-400">
      <Eye class="size-3"/>
      Предпросмотр — поля не сохраняются
    </div>
  </div>
</template>
