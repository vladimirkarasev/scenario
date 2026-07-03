<script setup lang="ts">
import type {RunHistoryEvent} from '@/modules/scenario/lib/scenario-player-types'

const props = defineProps<{
    events: RunHistoryEvent[]
}>()

const NODE_TYPE_LABELS: Record<string, string> = {
    block: 'блок',
    condition: 'условие',
    action: 'действие',
}

const AVATAR_COLORS = [
    'bg-blue-600',
    'bg-violet-600',
    'bg-emerald-600',
    'bg-amber-600',
    'bg-rose-600',
    'bg-teal-600',
]

function initials(actor: string | null): string {
    if (!actor) return '?'
    return actor
        .split(' ')
        .map((w) => w[0])
        .slice(0, 2)
        .join('')
        .toUpperCase()
}

const actorColors = new Map<string, string>()
let colorIdx = 0

function avatarColor(actor: string | null): string {
    const key = actor ?? '__system__'
    if (!actorColors.has(key)) {
        actorColors.set(key, AVATAR_COLORS[colorIdx++ % AVATAR_COLORS.length])
    }
    return actorColors.get(key)!
}

function actionLabel(event: RunHistoryEvent): string {
    if (event.type === 'transition') {
        const nodeLabel = NODE_TYPE_LABELS[event.node_type] ?? event.node_type
        const title = event.node_title ? ` «${event.node_title}»` : ''
        return `перешёл на ${nodeLabel}${title}`
    }
    if (event.type === 'field_filled') return 'заполнил поле'
    if (event.type === 'field_changed') return 'изменил поле'
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

    <div v-else class="relative">
        <!-- Вертикальная линия -->
        <div
            class="pointer-events-none absolute bottom-0 left-[15px] top-0 w-px bg-slate-200"
            aria-hidden="true"
        />

        <div
            v-for="(event, i) in props.events"
            :key="i"
            class="relative flex gap-3 pb-5 last:pb-0"
            :class="event.type === 'transition' && event.cancelled ? 'opacity-40' : ''"
        >
            <!-- Аватар -->
            <div
                class="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-[11px] font-bold text-white ring-2 ring-white"
                :class="avatarColor(event.actor)"
            >
                {{ initials(event.actor) }}
            </div>

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
                    class="mt-2 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-xs"
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
            </div>
        </div>
    </div>
</template>
