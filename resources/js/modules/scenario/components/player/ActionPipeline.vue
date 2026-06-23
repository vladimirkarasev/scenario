<script setup lang="ts">
import {computed} from 'vue'
import {CheckCircle2, Loader2, XCircle, Circle, Zap, RotateCcw} from 'lucide-vue-next'
import type {ActionStage, ActionStageStatus} from '@/modules/scenario/lib/scenario-player-types'

const props = withDefaults(defineProps<{
  title?: string
  stages?: ActionStage[]
  statuses?: Record<string, ActionStageStatus>
  failed?: boolean
  loading?: boolean
}>(), {
  title: 'Выполнение действий',
  stages: () => [],
  statuses: () => ({}),
  failed: false,
  loading: false,
})

const emit = defineEmits<{
  continue: []
  retry: []
}>()

function statusOf(code: string): ActionStageStatus {
  return props.statuses[code] ?? 'pending'
}

const STATUS_META: Record<ActionStageStatus, { label: string; badge: string }> = {
  pending: {label: 'Ожидает выполнения', badge: 'bg-slate-100 text-slate-400'},
  running: {label: 'В работе', badge: 'bg-violet-50 text-violet-600'},
  success: {label: 'Выполнено', badge: 'bg-emerald-50 text-emerald-600'},
  failed: {label: 'Ошибка', badge: 'bg-red-50 text-red-600'},
}

function statusMeta(code: string): { label: string; badge: string } {
  return STATUS_META[statusOf(code)]
}

const allDone = computed(() => props.stages.length > 0 && props.stages.every((s) => statusOf(s.code) === 'success'))
</script>

<template>
  <div
      class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-[0_1px_2px_rgba(0,0,0,0.04),0_4px_16px_rgba(0,0,0,0.04)]">
    <div class="h-[2px]"
         :class="failed ? 'bg-gradient-to-r from-red-400 to-red-500' : allDone ? 'bg-gradient-to-r from-emerald-400 to-emerald-500' : 'bg-gradient-to-r from-violet-400 to-violet-500'"/>

    <!-- Header -->
    <div class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-4">
      <div class="flex size-8 items-center justify-center rounded-lg bg-violet-50">
        <Zap class="size-4 text-violet-600"/>
      </div>
      <div class="min-w-0">
        <p class="text-[13px] font-semibold text-slate-900">{{ title }}</p>
        <p class="text-[12px] text-slate-400">
          {{ failed ? 'Возникла ошибка при выполнении' : allDone ? 'Все действия выполнены' : 'Выполняется…' }}
        </p>
      </div>
    </div>

    <!-- Stages (GitLab-style vertical pipeline) -->
    <ol class="px-5 py-4">
      <li
          v-for="(stage, index) in stages"
          :key="stage.code"
          class="relative flex items-center gap-3 pb-4 last:pb-0"
      >
        <!-- Connector line -->
        <span
            v-if="index < stages.length - 1"
            class="absolute left-[11px] top-6 h-[calc(100%-12px)] w-px"
            :class="statusOf(stage.code) === 'success' ? 'bg-emerald-200' : 'bg-slate-200'"
        />

        <!-- Status icon -->
        <span class="relative z-10 flex size-6 shrink-0 items-center justify-center rounded-full bg-white">
                    <Loader2 v-if="statusOf(stage.code) === 'running'" class="size-5 animate-spin text-violet-500"/>
                    <CheckCircle2 v-else-if="statusOf(stage.code) === 'success'" class="size-5 text-emerald-500"/>
                    <XCircle v-else-if="statusOf(stage.code) === 'failed'" class="size-5 text-red-500"/>
                    <Circle v-else class="size-5 text-slate-300"/>
                </span>

        <div class="flex min-w-0 flex-1 items-center justify-between gap-2">
                    <span
                        class="truncate text-[13px] font-medium"
                        :class="statusOf(stage.code) === 'pending' ? 'text-slate-400' : 'text-slate-700'"
                    >
                        {{ stage.name }}
                    </span>
          <span
              class="inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium"
              :class="statusMeta(stage.code).badge"
          >
                        <Loader2 v-if="statusOf(stage.code) === 'running'" class="size-2.5 animate-spin"/>
                        {{ statusMeta(stage.code).label }}
                    </span>
        </div>
      </li>
    </ol>

    <!-- Failure footer with retry / continue buttons -->
    <div v-if="failed" class="border-t border-slate-100 px-5 py-4">
      <p class="mb-3 text-[12px] text-red-600">
        Часть действий завершилась с ошибкой. Можно повторить с упавшего шага или продолжить опрос.
      </p>
      <div class="flex items-center gap-2">
        <button
            type="button"
            class="inline-flex h-9 items-center gap-1.5 rounded-xl bg-slate-900 px-4 text-[13px] font-medium text-white transition hover:bg-slate-800 disabled:opacity-50"
            :disabled="loading"
            @click="emit('retry')"
        >
          <RotateCcw class="size-3.5"/>
          Повторить
        </button>
        <button
            type="button"
            class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-slate-200 px-4 text-[13px] font-medium text-slate-600 transition hover:bg-slate-50 disabled:opacity-50"
            :disabled="loading"
            @click="emit('continue')"
        >
          Продолжить
        </button>
      </div>
    </div>
  </div>
</template>
