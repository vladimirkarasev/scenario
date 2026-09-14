<script setup lang="ts">
import {computed} from 'vue'
import {Handle, Position} from '@vue-flow/core'
import {
  Diamond,
  Eye,
  ExternalLink,
  MousePointerClick,
  Plus,
} from 'lucide-vue-next'
import {conditionAnswerIcon} from '@/modules/scenario/lib/condition-answer-icons'
import FlowNodeCard from '@/modules/scenario/components/flow/nodes/FlowNodeCard.vue'
import TiptapTextRenderer from '@/modules/scenario/components/tiptap/TiptapTextRenderer.vue'
import {hasTiptapDocumentContent} from '@/modules/scenario/lib/tiptap-gutenberg-doc'
import type {ScenarioBlockData} from '@/modules/scenario/lib/scenario-flow-document'

const props = withDefaults(defineProps<{
  id: string
  data: ScenarioBlockData
  selected?: boolean
}>(), {
  selected: false,
})

const boundaryAnchors = [
  {id: '1', horizontal: '!left-[35px]', vertical: '!top-[12.5%]'},
  {id: '2', horizontal: '!left-[105px]', vertical: '!top-[37.5%]'},
  {id: '3', horizontal: '!left-[175px]', vertical: '!top-[62.5%]'},
  {id: '4', horizontal: '!left-[245px]', vertical: '!top-[87.5%]'},
] as const

const hasContent = computed(() => hasTiptapDocumentContent(props.data.content))
const answers = computed(() => props.data.conditionBranches ?? [])
</script>

<template>
  <div class="scenario-flow-node relative w-[316px] pr-9">
    <Handle
        id="in"
        type="target"
        :position="Position.Left"
        class="scenario-flow-handle !size-4"
        :connectable-start="false"
        connectable-end
        aria-label="Входящий переход слева"
    />
    <Handle
        id="in_top"
        type="target"
        :position="Position.Top"
        class="scenario-flow-handle !left-[140px] !size-4"
        :connectable-start="false"
        connectable-end
        aria-label="Входящий переход сверху"
    />
    <Handle
        id="in_bottom"
        type="target"
        :position="Position.Bottom"
        class="scenario-flow-handle !left-[140px] !size-4"
        :connectable-start="false"
        connectable-end
        aria-label="Входящий переход снизу"
    />
    <Handle
        id="in_right"
        type="target"
        :position="Position.Right"
        class="scenario-flow-handle !right-9 !size-4"
        :connectable-start="false"
        connectable-end
        aria-label="Входящий переход справа"
    />
    <template v-for="anchor in boundaryAnchors" :key="anchor.id">
      <Handle
          :id="`in_left_${anchor.id}`"
          type="target"
          :position="Position.Left"
          class="scenario-flow-handle !size-4"
          :class="anchor.vertical"
          :connectable-start="false"
          connectable-end
          aria-label="Входящий переход слева"
      />
      <Handle
          :id="`in_top_${anchor.id}`"
          type="target"
          :position="Position.Top"
          class="scenario-flow-handle !size-4"
          :class="anchor.horizontal"
          :connectable-start="false"
          connectable-end
          aria-label="Входящий переход сверху"
      />
      <Handle
          :id="`in_bottom_${anchor.id}`"
          type="target"
          :position="Position.Bottom"
          class="scenario-flow-handle !size-4"
          :class="anchor.horizontal"
          :connectable-start="false"
          connectable-end
          aria-label="Входящий переход снизу"
      />
      <Handle
          :id="`in_right_${anchor.id}`"
          type="target"
          :position="Position.Right"
          class="scenario-flow-handle !right-9 !size-4"
          :class="anchor.vertical"
          :connectable-start="false"
          connectable-end
          aria-label="Входящий переход справа"
      />
    </template>

    <FlowNodeCard
        class="w-[280px]"
        :node-id="id"
        label="Условие"
        tone="sky"
        :selected="selected"
    >
      <template #icon>
        <Diamond class="size-2.5" />
      </template>

      <div
          v-if="hasContent"
          class="condition-node-content pointer-events-none relative mt-2 max-h-28 select-none overflow-hidden rounded-[16px] border border-slate-100 px-3 py-2.5 text-slate-700"
      >
        <TiptapTextRenderer :document="data.content" />
      </div>

      <div v-else class="mt-2 flex min-h-20 flex-col items-center justify-center gap-1.5 rounded-[16px] border border-dashed border-sky-200 text-center">
        <MousePointerClick :size="16" class="text-sky-300" />
        <span class="text-[10px] leading-snug text-slate-300">
          Дважды кликните,<br>чтобы описать условие
        </span>
      </div>

      <div class="mt-2 flex flex-col gap-1.5">
          <div
              v-for="answer in answers"
              :key="answer.id"
              class="group/answer relative min-w-0 rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-slate-700 transition-colors hover:border-sky-300 hover:bg-sky-50"
          >
            <div class="flex min-h-5 min-w-0 items-center gap-1.5 text-[9px] font-semibold">
              <span class="flex size-4 shrink-0 items-center justify-center rounded-md bg-sky-100 text-sky-600">
                <component :is="conditionAnswerIcon(answer.icon)" v-if="conditionAnswerIcon(answer.icon)" class="size-2.5" />
                <Diamond v-else class="size-2.5" />
              </span>
              <span class="min-w-0 flex-1 truncate">{{ answer.label || 'Ответ' }}</span>
              <span v-if="answer.action === 'url'" class="inline-flex shrink-0 items-center gap-1 text-[8px] font-bold uppercase tracking-wide text-sky-600">
                Открыть
                <ExternalLink class="size-3" />
              </span>
            </div>
            <div
                class="mt-1 flex min-w-0 items-center gap-1 border-t border-slate-100 pt-1 text-[8px] font-normal text-slate-400"
                :title="answer.condition || 'true'"
            >
              <Eye class="size-2.5 shrink-0 text-sky-400" />
              <span class="shrink-0">Показ:</span>
              <code class="min-w-0 truncate font-mono text-[8px] text-slate-500">{{ answer.condition || 'true' }}</code>
            </div>
            <span
                v-if="answer.action === 'transition'"
                class="pointer-events-none absolute -right-7 top-1/2 h-px w-7 -translate-y-1/2 bg-sky-300 transition-colors group-hover/answer:bg-sky-500"
            />
            <Handle
                v-if="answer.action === 'transition'"
                :id="answer.id"
                type="source"
                :position="Position.Right"
                class="condition-answer-handle scenario-flow-handle !pointer-events-auto !right-[-32px] !size-4"
                connectable-start
                :connectable-end="false"
                :aria-label="`Переход: ${answer.label || 'Ответ'}`"
            />
          </div>

          <div v-if="!answers.length" class="flex items-center justify-center gap-1 rounded-xl border border-dashed border-slate-200 py-2 text-[9px] text-slate-400">
            <Plus class="size-3" />
            Добавьте ответы в редакторе
          </div>
      </div>
    </FlowNodeCard>
  </div>
</template>

<style>@import './connector.css';</style>

<style scoped>
.condition-node-content :deep(.tiptap-content) {
  color: inherit;
  font-size: 10px;
  line-height: 1.35;
}

.condition-node-content :deep(.tiptap-content *) {
  margin-block: 0.125rem;
}

.condition-node-content :deep(.tiptap-content h1),
.condition-node-content :deep(.tiptap-content h2),
.condition-node-content :deep(.tiptap-content h3),
.condition-node-content :deep(.tiptap-content h4),
.condition-node-content :deep(.tiptap-content h5),
.condition-node-content :deep(.tiptap-content h6) {
  color: inherit;
  font-size: 11px;
  line-height: 1.3;
}

.condition-answer-handle {
  border: 3px solid white !important;
  border-radius: 9999px !important;
  background: rgb(14 165 233) !important;
  box-shadow: 0 0 0 2px rgb(186 230 253), 0 3px 8px rgb(14 165 233 / 0.28) !important;
  transition: transform 0.15s ease, box-shadow 0.15s ease !important;
}

.condition-answer-handle:hover {
  transform: translate(50%, -50%) scale(1.18) !important;
  box-shadow: 0 0 0 4px rgb(224 242 254), 0 4px 12px rgb(14 165 233 / 0.4) !important;
}
</style>
