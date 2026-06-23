<script setup lang="ts">
import { computed, ref } from 'vue'
import type { ScenarioRenderedConditionOption } from '@/modules/scenario/lib/scenario-player-types'

const props = defineProps<{
    question: string
    options: ScenarioRenderedConditionOption[]
    loading?: boolean
}>()

const emit = defineEmits<{
    select: [targetNodeId: string]
}>()

const safeOptions = computed(() => (props.options ?? []).filter(Boolean))

const selected = ref<string | null>(null)

function pick(targetNodeId: string) {
    selected.value = targetNodeId
    emit('select', targetNodeId)
}
</script>

<template>
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <!-- Header -->
        <div class="border-b border-slate-100 px-6 py-5">
            <h2 class="text-[18px] font-semibold leading-snug text-slate-900">
                {{ question || 'Выберите вариант' }}
            </h2>
        </div>

        <!-- Options -->
        <div class="flex flex-wrap items-center gap-2 px-6 py-5">
            <div
                v-if="!safeOptions.length"
                class="rounded-xl border border-dashed border-slate-200 px-4 py-3 text-[13px] text-slate-400"
            >
                Для этого условия не настроены переходы.
            </div>

            <button
                v-for="(option, index) in safeOptions"
                v-else
                :key="option?.targetNodeId ?? `option-${index}`"
                type="button"
                :disabled="loading"
                class="inline-flex h-9 items-center rounded-xl border px-4 text-[13px] font-medium transition disabled:pointer-events-none disabled:opacity-50"
                :class="selected === option.targetNodeId
                    ? 'border-blue-600 bg-blue-600 text-white shadow-sm'
                    : 'border-slate-200 bg-white text-slate-700 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700'"
                @click="pick(option.targetNodeId)"
            >
                {{ option.label }}
            </button>
        </div>
    </div>
</template>
