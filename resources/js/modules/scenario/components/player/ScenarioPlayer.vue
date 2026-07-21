<script setup lang="ts">
import {computed, nextTick, onMounted, onUnmounted, ref, watch} from 'vue'
import {Skeleton} from '@/components/ui/skeleton'
import BlockRenderer from '@/modules/scenario/components/player/BlockRenderer.vue'
import ConditionRenderer from '@/modules/scenario/components/player/ConditionRenderer.vue'
import SurveyBlockRenderer from '@/modules/scenario/components/player/SurveyBlockRenderer.vue'
import TiptapTextRenderer from '@/modules/scenario/components/tiptap/TiptapTextRenderer.vue'
import ScenarioTimelineEntry from '@/modules/scenario/components/player/ScenarioTimelineEntry.vue'
import ActionPipeline from '@/modules/scenario/components/player/ActionPipeline.vue'
import {useScenarioPlayer} from '@/modules/scenario/composables/useScenarioPlayer'
import type {
  SurveyBlock,
  ScenarioRenderedAction,
  ScenarioRenderedBlock,
  ScenarioRenderedCondition,
  ScenarioRenderedEnd,
  ScenarioRunPayload,
} from '@/modules/scenario/lib/scenario-player-types'
import {AlertCircle, CheckCircle2, CornerDownRight, CornerUpLeft, XCircle} from 'lucide-vue-next'

const props = defineProps<{
  scenarioId?: string | null
  runId?: string | null
  scenarioVersionId?: string | null
  initialContext?: Record<string, unknown>
}>()

const emit = defineEmits<{
  'update:run': [run: ScenarioRunPayload | null]
}>()

const {
  loading,
  error,
  run,
  rendered,
  context,
  pastTimelineRows,
  currentTimeline,
  completed,
  failed,
  fieldErrors,
  actionStages,
  pipelineFailed,
  createRun,
  loadRun,
  continueRun,
  retryAction,
  jumpTo,
  subscribe,
  unsubscribe,
} = useScenarioPlayer()

const bottomAnchor = ref<HTMLElement | null>(null)

async function handleContinue(input: Record<string, unknown>, targetNodeId?: string | null) {
  await continueRun(input, targetNodeId)
  await nextTick()
  bottomAnchor.value?.scrollIntoView({behavior: 'smooth', block: 'start'})
}

watch(run, (val) => emit('update:run', val))

onMounted(async () => {
  if (props.runId) {
    await loadRun(props.runId)
    if (run.value?.id) await subscribe(run.value.id)
    return
  }

  if (props.scenarioId) {
    await createRun({
      scenarioId: props.scenarioId,
      scenarioVersionId: props.scenarioVersionId ?? null,
      context: props.initialContext ?? {},
    })
    if (run.value?.id) await subscribe(run.value.id)
  }
})

onUnmounted(() => {
  unsubscribe()
})

function asBlock(value: unknown): ScenarioRenderedBlock {
  return value as ScenarioRenderedBlock
}

function asCondition(value: unknown): ScenarioRenderedCondition {
  return value as ScenarioRenderedCondition
}

function asEnd(value: unknown): ScenarioRenderedEnd {
  return value as ScenarioRenderedEnd
}

function asAction(value: unknown): ScenarioRenderedAction {
  return value as ScenarioRenderedAction
}

function nextTargetNodeId(index: number): string | null {
  for (let i = index + 1; i < pastTimelineRows.value.length; i++) {
    const row = pastTimelineRows.value[i]
    if (row.type === 'entry') return row.entry.node_id
  }
  return currentTimeline.value?.node_id ?? null
}

const endBlocks = computed(() =>
    ((asEnd(rendered.value)?.blocks ?? []) as SurveyBlock[]).filter(Boolean),
)

const activeDraftKey = computed(() => {
  const runId = run.value?.id
  const nodeId = run.value?.current_node_id
  if (!runId || !nodeId) return null
  return `scenario-run:${runId}:${nodeId}`
})
</script>

<template>
  <div class="flex flex-col gap-3">

    <!-- Error banner -->
    <div v-if="error" class="flex items-start gap-2.5 rounded-lg border border-red-200 bg-red-50 px-4 py-3">
      <AlertCircle class="mt-0.5 size-3.5 shrink-0 text-red-500"/>
      <div>
        <p class="text-[12px] font-semibold text-red-800">Ошибка</p>
        <p class="mt-0.5 text-[12px] text-red-600">{{ error }}</p>
      </div>
    </div>

    <!-- Past timeline (с разделителями границ связанных сценариев) -->
    <div class="flex flex-col gap-2">
      <template v-for="(row, index) in pastTimelineRows" :key="row.type === 'divider' ? row.key : row.entry.key">
        <!-- Разделитель: Начало / Конец связанного сценария -->
        <div
            v-if="row.type === 'divider'"
            class="flex items-center gap-2 py-0.5 text-[11px] font-medium"
            :class="row.kind === 'start' ? 'text-emerald-600' : 'text-slate-400'"
        >
          <div class="h-px flex-1 rounded-full" :class="row.kind === 'start' ? 'bg-emerald-200/70' : 'bg-slate-200'"/>
          <span
              class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 ring-1"
              :class="row.kind === 'start'
                ? 'bg-emerald-50 ring-emerald-100'
                : 'bg-slate-50 ring-slate-200'"
          >
            <component :is="row.kind === 'start' ? CornerDownRight : CornerUpLeft" class="size-3 shrink-0"/>
            <span class="opacity-80">{{ row.kind === 'start' ? 'Начало сценария' : 'Конец сценария' }}</span>
            <span class="font-semibold">{{ row.scenarioName }}</span>
            <span v-if="row.versionName" class="opacity-60">: {{ row.versionName }}</span>
          </span>
          <div class="h-px flex-1 rounded-full" :class="row.kind === 'start' ? 'bg-emerald-200/70' : 'bg-slate-200'"/>
        </div>

        <!-- Обычная запись таймлайна -->
        <ScenarioTimelineEntry
            v-else
            :entry="row.entry"
            :loading="loading"
            :selected-target-node-id="nextTargetNodeId(index)"
            @jump="jumpTo"
        />
      </template>
    </div>

    <!-- Completed -->
    <div v-if="completed"
         class="overflow-hidden rounded-xl border border-emerald-200/80 bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04),0_4px_16px_rgba(0,0,0,0.04)]">
      <div class="h-[2px] bg-gradient-to-r from-emerald-400 to-emerald-500"/>
      <div class="px-5 py-5 text-center">
        <div class="mx-auto mb-3 flex h-10 w-10 items-center justify-center rounded-full bg-emerald-100">
          <CheckCircle2 class="size-5 text-emerald-600"/>
        </div>
        <h2 class="text-[15px] font-semibold tracking-[-0.01em] text-slate-900">
          {{ asEnd(rendered)?.title ?? 'Все шаги успешно пройдены' }}
        </h2>
        <TiptapTextRenderer
            v-if="asEnd(rendered)?.description"
            :html="asEnd(rendered)?.description ?? ''"
            class="prose prose-sm mx-auto mt-2 max-w-none text-[12.5px] text-slate-500"
        />
      </div>
      <div v-if="endBlocks.length" class="grid gap-3 border-t border-slate-100 px-5 py-4">
        <SurveyBlockRenderer
            v-for="(block, index) in endBlocks"
            :key="block?.id ?? `end-block-${index}`"
            :block="block"
            :context="context"
            :form-data="{}"
        />
      </div>
    </div>

    <!-- Failed -->
    <div v-if="failed && !completed"
         class="overflow-hidden rounded-xl border border-red-200/80 bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04),0_4px_16px_rgba(0,0,0,0.04)]">
      <div class="h-[2px] bg-gradient-to-r from-red-400 to-red-500"/>
      <div class="px-5 py-5 text-center">
        <div class="mx-auto mb-3 flex h-10 w-10 items-center justify-center rounded-full bg-red-100">
          <XCircle class="size-5 text-red-500"/>
        </div>
        <h2 class="text-[15px] font-semibold text-slate-900">Сценарий завершился с ошибкой</h2>
        <p class="mt-1 text-[12px] text-slate-500">Выполнение остановлено. Попробуйте начать заново.</p>
      </div>
    </div>

    <!-- Active block -->
    <BlockRenderer
        v-if="rendered && (rendered as Record<string, unknown>).type === 'block' && !completed && !failed"
        :title="asBlock(rendered).title"
        :blocks="asBlock(rendered).blocks"
        :layout-document="asBlock(rendered).layoutDocument"
        :context="context"
        :loading="loading"
        :field-errors="fieldErrors"
        :draft-key="activeDraftKey"
        :initial-values="(context[run?.current_node_id ?? ''] as Record<string, unknown> | undefined) ?? null"
        @continue="handleContinue"
    />

    <!-- Active condition -->
    <ConditionRenderer
        v-if="rendered && (rendered as Record<string, unknown>).type === 'condition' && !completed && !failed"
        :question="asCondition(rendered).question"
        :options="asCondition(rendered).options"
        :loading="loading"
        @select="(targetNodeId: string) => handleContinue({}, targetNodeId)"
    />

    <!-- Active action pipeline (wait_for_result) -->
    <ActionPipeline
        v-if="rendered && (rendered as Record<string, unknown>).type === 'action' && !completed && !failed"
        :title="String(asAction(rendered).data?.title || 'Выполнение действий')"
        :stages="asAction(rendered).stages ?? []"
        :statuses="actionStages"
        :failed="pipelineFailed || Boolean(asAction(rendered).failed)"
        :loading="loading"
        @continue="handleContinue({})"
        @retry="retryAction()"
    />

    <!-- Loading / waiting -->
    <div
        v-if="!completed && !failed && !rendered"
        class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04),0_4px_16px_rgba(0,0,0,0.04)]"
    >
      <div class="h-[2px] bg-gradient-to-r from-slate-200 to-slate-300"/>
      <div class="px-5 py-4">
        <Skeleton v-if="loading" class="mb-1.5 h-4 w-40 rounded-md"/>
        <Skeleton v-if="loading" class="h-3 w-28 rounded-md"/>
        <template v-else>
          <p class="text-[13px] font-medium text-slate-700">Подготовка сценария</p>
          <p class="mt-0.5 text-[12px] text-slate-400">Ожидаем активный узел...</p>
        </template>
      </div>
      <div v-if="loading" class="grid gap-3 border-t border-slate-100 px-5 pb-4 pt-3">
        <div class="space-y-1.5">
          <Skeleton class="h-3 w-20 rounded-md"/>
          <Skeleton class="h-8 w-full rounded-lg"/>
        </div>
        <div class="space-y-1.5">
          <Skeleton class="h-3 w-24 rounded-md"/>
          <Skeleton class="h-8 w-full rounded-lg"/>
        </div>
        <Skeleton class="mt-0.5 h-8 w-28 rounded-lg"/>
      </div>
    </div>

    <div ref="bottomAnchor" class="h-px"/>
  </div>
</template>
