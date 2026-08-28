<script setup lang="ts">
import type {Component} from 'vue'
import {ArrowRight, CheckCircle2, GitBranch, Pencil, Play, RotateCcw, Share2, XCircle, Zap} from 'lucide-vue-next'
import {Tooltip, TooltipContent, TooltipProvider, TooltipTrigger} from '@/components/ui/tooltip'
import type {RunHistoryEvent} from '@/modules/scenario/lib/scenario-player-types'

const props = defineProps<{
    events: RunHistoryEvent[]
}>()

const NODE_TYPE_LABELS: Record<string, string> = {
    block: 'блок',
    question: 'вопрос',
    condition: 'условие',
    action: 'действие',
    end: 'конец',
    scenario_link: 'переход',
    start: 'старт',
}

const ACTION_EVENT_TYPES = ['action_completed', 'action_failed']

interface EventVisual {
    icon: Component
    bg: string
}

const EVENT_VISUALS: Record<string, EventVisual> = {
    transition: {icon: ArrowRight, bg: 'bg-blue-600'},
    field_filled: {icon: Pencil, bg: 'bg-violet-600'},
    field_changed: {icon: Pencil, bg: 'bg-violet-600'},
    condition_evaluated: {icon: GitBranch, bg: 'bg-amber-600'},
    action_completed: {icon: Zap, bg: 'bg-emerald-600'},
    action_failed: {icon: Zap, bg: 'bg-red-600'},
    scenario_link_followed: {icon: Share2, bg: 'bg-teal-600'},
    run_started: {icon: Play, bg: 'bg-blue-600'},
    run_completed: {icon: CheckCircle2, bg: 'bg-emerald-600'},
    run_failed: {icon: XCircle, bg: 'bg-red-600'},
    cancelled: {icon: RotateCcw, bg: 'bg-slate-500'},
}

const DEFAULT_VISUAL: EventVisual = {icon: ArrowRight, bg: 'bg-slate-500'}

function visual(event: RunHistoryEvent): EventVisual {
    return EVENT_VISUALS[event.type] ?? DEFAULT_VISUAL
}

const EVENT_TOOLTIPS: Record<string, string> = {
    transition: 'Переход на другой узел сценария',
    field_filled: 'Поле опроса заполнено',
    field_changed: 'Значение поля изменено',
    condition_evaluated: 'Оценка условия / выбор ветки',
    action_completed: 'Действие выполнено успешно',
    action_failed: 'Ошибка при выполнении действия',
    scenario_link_followed: 'Переход в связанный сценарий',
    run_started: 'Сценарий запущен',
    run_completed: 'Сценарий завершён',
    run_failed: 'Сценарий завершился с ошибкой',
    cancelled: 'Возврат к более раннему шагу, дальнейший путь отменён',
}

function iconTooltip(event: RunHistoryEvent): string {
    return EVENT_TOOLTIPS[event.type] ?? event.type
}

function nodeRef(event: RunHistoryEvent): string {
    const rawLabel = (event.node_type && NODE_TYPE_LABELS[event.node_type]) ?? event.node_type ?? 'узел'
    if (event.node_title) {
        return `${rawLabel} «${event.node_title}»`
    }
    const capitalized = rawLabel.charAt(0).toUpperCase() + rawLabel.slice(1)
    return event.node_id ? `${capitalized} (#${event.node_id})` : capitalized
}

function actionLabel(event: RunHistoryEvent): string {
    if (event.type === 'transition') return `перешёл на ${nodeRef(event)}`
    if (event.type === 'field_filled') return 'заполнил поле'
    if (event.type === 'field_changed') return 'изменил поле'
    if (event.type === 'condition_evaluated') {
        return event.condition_label
            ? `выбрал вариант «${event.condition_label}»`
            : 'прошёл условие'
    }
    if (event.type === 'action_completed') return 'выполнил действие'
    if (event.type === 'action_failed') return 'ошибка при выполнении действия'
    if (event.type === 'scenario_link_followed') {
        return event.target_scenario_name
            ? `перешёл в связанный сценарий «${event.target_scenario_name}»`
            : 'перешёл в связанный сценарий'
    }
    if (event.type === 'run_started') return 'начал сценарий'
    if (event.type === 'run_completed') return 'завершил сценарий'
    if (event.type === 'run_failed') return 'сценарий завершился ошибкой'
    if (event.type === 'cancelled') return `отменил переход, вернулся к ${nodeRef(event)}`
    return event.type
}

function formatTime(iso: string | null): string {
    if (!iso) return ''
    const d = new Date(iso)
    return d.toLocaleString('ru-RU', {
        day: 'numeric',
        month: 'long',
        hour: '2-digit',
        minute: '2-digit',
    })
}

function formatValue(value: unknown): string {
    if (value === null || value === undefined || value === '') return '—'
    if (typeof value === 'boolean') return value ? 'Да' : 'Нет'
    if (Array.isArray(value)) return value.join(', ')
    return String(value)
}
</script>

<template>
    <div v-if="!props.events.length" class="py-10 text-center text-sm text-slate-400">
        История пуста
    </div>

    <TooltipProvider v-else :delay-duration="200">
    <div class="relative">
        <!-- Вертикальная линия -->
        <div
            class="pointer-events-none absolute bottom-0 left-[15px] top-0 w-px bg-slate-200"
            aria-hidden="true"
        />

        <div
            v-for="(event, i) in props.events"
            :key="i"
            class="relative flex gap-3 pb-5 last:pb-0"
        >
            <!-- Маркер типа события -->
            <Tooltip>
                <TooltipTrigger as-child>
                    <div
                        class="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-white ring-2 ring-white"
                        :class="visual(event).bg"
                    >
                        <component :is="visual(event).icon" class="size-4"/>
                    </div>
                </TooltipTrigger>
                <TooltipContent side="right">
                    {{ iconTooltip(event) }}
                </TooltipContent>
            </Tooltip>

            <!-- Контент -->
            <div class="min-w-0 flex-1 pt-1">
                <!-- Заголовок -->
                <div class="flex flex-wrap items-baseline gap-x-1.5 gap-y-0.5">
                    <span class="text-sm font-semibold text-slate-900">
                        {{ event.actor ?? 'Система' }}
                    </span>
                    <span class="text-sm text-slate-500">{{ actionLabel(event) }}</span>
                    <span class="text-slate-300">·</span>
                    <span class="text-xs text-slate-400">{{ formatTime(event.at) }}</span>
                </div>

                <!-- Детали изменения поля -->
                <div
                    v-if="event.type === 'field_filled' || event.type === 'field_changed'"
                    class="mt-2 rounded-lg border border-violet-100 bg-violet-50/60 px-3 py-2 text-xs"
                >
                    <div class="mb-1 font-medium text-slate-600">
                        {{ event.field_label }}
                        <span class="font-normal text-slate-400">({{ event.field_id }})</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-1.5">
                        <template v-if="event.type === 'field_changed'">
                            <span class="rounded bg-red-50 px-1.5 py-0.5 text-red-500 line-through">
                                {{ formatValue(event.old_value) }}
                            </span>
                            <span class="text-slate-400">→</span>
                        </template>
                        <span class="rounded bg-emerald-50 px-1.5 py-0.5 font-medium text-emerald-700">
                            {{ formatValue(event.new_value) }}
                        </span>
                    </div>
                </div>

                <!-- Детали условия -->
                <div
                    v-else-if="event.type === 'condition_evaluated'"
                    class="mt-2 rounded-lg border border-amber-100 bg-amber-50/60 px-3 py-2 text-xs text-amber-700"
                >
                    {{ event.condition_mode === 'auto' ? 'Автоматический выбор' : 'Выбор оператора' }}
                </div>

                <!-- Детали результата действия -->
                <div
                    v-else-if="ACTION_EVENT_TYPES.includes(event.type)"
                    class="mt-2 rounded-lg border px-3 py-2 text-xs"
                    :class="event.type === 'action_failed' ? 'border-red-100 bg-red-50/60' : 'border-emerald-100 bg-emerald-50/60'"
                >
                    <div class="mb-1 font-medium text-slate-600">
                        {{ event.action_name ?? event.code }}
                    </div>
                    <span
                        class="rounded px-1.5 py-0.5 font-medium"
                        :class="event.type === 'action_failed' ? 'bg-red-50 text-red-600' : 'bg-emerald-50 text-emerald-700'"
                    >
                        {{ event.type === 'action_failed' ? (event.error ?? 'Ошибка') : 'Успешно' }}
                    </span>
                </div>
            </div>
        </div>
    </div>
    </TooltipProvider>
</template>
