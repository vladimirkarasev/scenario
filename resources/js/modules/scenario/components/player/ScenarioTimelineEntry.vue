<script setup lang="ts">
import {computed} from 'vue'
import {X} from 'lucide-vue-next'
import BlockRenderer from '@/modules/scenario/components/player/BlockRenderer.vue'
import ActionPipeline from '@/modules/scenario/components/player/ActionPipeline.vue'
import TiptapTextRenderer from '@/modules/scenario/components/tiptap/TiptapTextRenderer.vue'
import {hasTiptapDocumentContent} from '@/modules/scenario/lib/tiptap-gutenberg-doc'
import type {
  ActionStageStatus,
  ScenarioRenderedAction,
  ScenarioRenderedBlock,
  ScenarioRenderedCondition,
  ScenarioTimelineEntry,
} from '@/modules/scenario/lib/scenario-player-types'

const props = defineProps<{
  entry: ScenarioTimelineEntry
  loading?: boolean
  selectedTargetNodeId?: string | null
}>()

const emit = defineEmits<{
  jump: [nodeId: string]
}>()

function asBlock(value: unknown): ScenarioRenderedBlock {
  return value as ScenarioRenderedBlock
}

function asCondition(value: unknown): ScenarioRenderedCondition {
  return value as ScenarioRenderedCondition
}

function asAction(value: unknown): ScenarioRenderedAction {
  return value as ScenarioRenderedAction
}

function conditionTitle(): string {
  return asCondition(props.entry.rendered).question || 'Условие'
}

const hasConditionContent = computed(() => hasTiptapDocumentContent(asCondition(props.entry.rendered)?.content))

const pastActionStatuses = computed<Record<string, ActionStageStatus>>(() => {
  const result: Record<string, ActionStageStatus> = {}
  for (const stage of asAction(props.entry.rendered).stages ?? []) {
    result[stage.code] = 'success'
  }
  return result
})
</script>

<template>
  <!-- Block timeline entry -->
  <div
      v-if="entry.rendered && (entry.rendered as Record<string, unknown>).type === 'block'"
      class="past-entry"
  >
    <BlockRenderer
        :title="asBlock(entry.rendered).title"
        :hide-title="asBlock(entry.rendered).hideTitle"
        :blocks="asBlock(entry.rendered).blocks"
        :layout-document="asBlock(entry.rendered).layoutDocument"
        :context="entry.context"
        :initial-values="(entry.context[entry.node_id] as Record<string, unknown> | undefined) ?? null"
        readonly
        disabled
    >
      <template #footer>
        <button
            type="button"
            class="inline-flex h-9 items-center gap-1.5 rounded-xl bg-red-50 px-4 text-[13px] font-medium text-red-600 transition hover:bg-red-100 disabled:opacity-50"
            :disabled="loading"
            @click="emit('jump', entry.node_id)"
        >
          <X class="size-3.5"/>
          Отмена
        </button>
      </template>
    </BlockRenderer>
  </div>

  <!-- Action pipeline timeline entry -->
  <div
      v-else-if="entry.rendered && (entry.rendered as Record<string, unknown>).type === 'action'"
      class="past-entry"
  >
    <ActionPipeline
        :title="String(asAction(entry.rendered).data?.title || 'Выполнение действий')"
        :hide-title="Boolean(asAction(entry.rendered).data?.hideTitle ?? true)"
        :stages="asAction(entry.rendered).stages ?? []"
        :statuses="pastActionStatuses"
    />
  </div>

  <!-- Condition timeline entry -->
  <div
      v-else-if="entry.rendered && (entry.rendered as Record<string, unknown>).type === 'condition'"
      class="past-entry overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
  >
    <!-- Header -->
    <div v-if="!asCondition(entry.rendered).hideTitle" class="border-b border-slate-100 px-6 py-5">
      <h2 class="text-[18px] font-semibold leading-snug text-slate-900">
        {{ conditionTitle() }}
      </h2>
    </div>

    <div v-if="hasConditionContent" class="border-b border-slate-100 px-6 py-5">
      <TiptapTextRenderer :document="asCondition(entry.rendered).content" />
    </div>

    <div class="flex flex-wrap items-center gap-2 px-6 py-5">
            <span
                v-for="(option, index) in asCondition(entry.rendered).options"
                :key="option?.targetNodeId ?? `past-option-${index}`"
                class="inline-flex h-9 items-center rounded-xl border px-4 text-[13px] font-medium"
                :class="selectedTargetNodeId && option.targetNodeId === selectedTargetNodeId
                    ? 'border-blue-600 bg-blue-600 text-white shadow-sm'
                    : 'border-slate-200 bg-slate-50 text-slate-400'"
            >
                {{ option.label }}
            </span>
    </div>

    <!-- Footer -->
    <div class="border-t border-slate-100 px-6 py-4">
      <button
          type="button"
          class="inline-flex h-9 items-center gap-1.5 rounded-xl bg-red-50 px-4 text-[13px] font-medium text-red-600 transition hover:bg-red-100 disabled:opacity-50"
          :disabled="loading"
          @click="emit('jump', entry.node_id)"
      >
        <X class="size-3.5"/>
        Отмена
      </button>
    </div>
  </div>
</template>

<style scoped>
.past-entry {
  opacity: 0.7;
  transition: opacity 0.2s ease;

}

.past-entry:hover {
  opacity: 1;
}
</style>
