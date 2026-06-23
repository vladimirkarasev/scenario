<script setup lang="ts">
import { computed, toRef, useSlots } from 'vue'
import {Loader2, ArrowRight} from 'lucide-vue-next'
import SurveyBlockRenderer from '@/modules/scenario/components/player/SurveyBlockRenderer.vue'
import { useBlockForm } from '@/modules/scenario/composables/useBlockForm'
import type { SurveyBlock } from '@/modules/scenario/lib/scenario-player-types'

const props = withDefaults(defineProps<{
    title: string
    blocks: SurveyBlock[]
    context: Record<string, unknown>
    loading?: boolean
    readonly?: boolean
    disabled?: boolean
    fieldErrors?: Record<string, string[]>
    continueLabel?: string
    draftKey?: string | null
    initialValues?: Record<string, unknown> | null
}>(), {
    continueLabel: 'Далее',
    draftKey: null,
    initialValues: null,
})

const emit = defineEmits<{
    continue: [payload: Record<string, unknown>]
}>()

const slots = useSlots()
const safeBlocks = computed(() => (props.blocks ?? []).filter(Boolean))
const hasContinueButton = computed(() => !props.readonly)
const hasFooter = computed(() => hasContinueButton.value || !!slots.footer)

const { formData, errors, submit } = useBlockForm(
    toRef(props, 'blocks'),
    toRef(props, 'fieldErrors'),
    toRef(props, 'draftKey'),
    toRef(props, 'initialValues'),
)

function handleContinue() {
    submit((data) => emit('continue', data))
}
</script>

<template>
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <!-- Header -->
        <div class="border-b border-slate-100 px-6 py-5">
            <h2 class="text-[18px] font-semibold leading-snug text-slate-900">
                {{ title || 'Шаг' }}
            </h2>
        </div>

        <!-- Content -->
        <div v-if="safeBlocks.length" class="grid gap-5 px-6 py-5">
            <SurveyBlockRenderer
                v-for="(block, index) in safeBlocks"
                :key="block?.id ?? `block-${index}`"
                :block="block"
                :context="context"
                :form-data="formData"
                :errors="errors"
                :disabled="disabled"
            />
        </div>
        <div v-else class="flex flex-col items-center py-12 text-center">
            <p class="text-[13px] text-slate-400">Нет полей для отображения</p>
        </div>

        <!-- Footer -->
        <div v-if="hasFooter" class="border-t border-slate-100 px-6 py-4">
            <button
                v-if="hasContinueButton"
                type="button"
                :disabled="loading"
                class="inline-flex px-4 items-center justify-center gap-2 rounded-xl bg-blue-600 py-2.5 text-[13px] font-semibold text-white transition hover:bg-blue-700 active:scale-[0.99] disabled:pointer-events-none disabled:opacity-60"
                @click="handleContinue"
            >
                <Loader2 v-if="loading" class="size-3.5 animate-spin" />
                <template v-else>{{ continueLabel }} <ArrowRight class="size-3.5" /></template>
            </button>
            <div v-if="slots.footer" :class="hasContinueButton ? 'mt-3' : ''">
                <slot name="footer" />
            </div>
        </div>
    </div>
</template>
