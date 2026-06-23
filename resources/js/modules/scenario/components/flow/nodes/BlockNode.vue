<script setup>
import { computed } from 'vue'
import { Handle, Position } from '@vue-flow/core'
import SurveyBlockPreview from '@/modules/scenario/components/player/SurveyBlockPreview.vue'
import { MousePointerClick } from 'lucide-vue-next'

const props = defineProps({
    data:     { type: Object,  default: () => ({}) },
    selected: { type: Boolean, default: false },
})

const cfg = {
    frame: 'rounded-2xl border-[3px] border-sky-500 bg-white text-slate-800 shadow-[0_8px_24px_rgba(14,165,233,0.12)]',
    size:  'w-56 min-h-[96px] px-4 py-3.5',
}

function toRichTextProps(value) {
    if (!value) return { document: { type: 'doc', content: [{ type: 'paragraph' }] } }
    if (typeof value === 'object' && value?.type === 'doc') return { document: value }
    if (typeof value === 'string') {
        try {
            const parsed = JSON.parse(value)
            if (parsed?.type === 'doc') return { document: parsed }
        } catch { /* not valid JSON, fall through */ }
        return { html: value }
    }
    return { document: { type: 'doc', content: [{ type: 'paragraph' }] } }
}

const blocks = computed(() => {
    const result = []

    if (props.data.text) {
        result.push({ id: 'node_text', type: 'rich_text', props: toRichTextProps(props.data.text) })
    }

    for (const field of props.data.fields ?? []) {
        const type = field.type ?? 'input'
        if (type === 'rich_text' || type === 'collapse') {
            result.push({ id: field.id, type: 'rich_text', props: toRichTextProps(field.value) })
        } else {
            result.push({
                id: field.id,
                type: ['textarea', 'number', 'select', 'datetime', 'hidden'].includes(type) ? type : 'input',
                props: {
                    name: field.name,
                    label: field.label,
                    placeholder: field.placeholder,
                    defaultValue: field.value,
                    required: field.required,
                    rows: field.rows,
                    multiple: field.multiple,
                    allowRootSelection: field.allowRootSelection,
                    options: field.options,
                },
            })
        }
    }

    return result
})
</script>

<template>
    <div class="scenario-flow-node relative">
<Handle id="in" type="source" :position="Position.Top" class="scenario-flow-handle !h-4 !w-full" connectable-start connectable-end />
        <Handle id="left" type="source" :position="Position.Left" class="scenario-flow-handle !h-full !w-4" connectable-start connectable-end />

        <div class="relative flex flex-col transition" :class="[cfg.frame, cfg.size]">
            <div v-if="data.title" class="absolute top-[2px] left-3 bg-white px-1 font-mono text-[10px] leading-none text-slate-400">
                {{ data.variable }}
            </div>
            <div
                v-if="data.skipInSurvey"
                class="absolute -top-3 z-10 right-3 rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-medium leading-none text-amber-700 shadow-sm"
            >
                Пропущен в опросе
            </div>

            <!-- Content -->
            <div v-if="blocks.length" class="w-full space-y-2 text-left pointer-events-none select-none">
                <SurveyBlockPreview v-for="block in blocks" :key="block.id" :block="block" />
            </div>

            <!-- Empty state -->
            <div v-else class="flex flex-1 flex-col items-center justify-center gap-1.5 text-center">
                <MousePointerClick :size="16" class="text-sky-300" />
                <span class="text-[11px] leading-snug text-slate-300">
                    Кликните 2 раза<br>для редактирования блока
                </span>
            </div>
        </div>

        <div class="scenario-flow-connector scenario-flow-connector--rounded" :class="{ 'scenario-flow-connector--selected': selected }" />

        <Handle id="out" type="source" :position="Position.Bottom" class="scenario-flow-handle !h-4 !w-full" connectable-start connectable-end />
        <Handle id="right" type="source" :position="Position.Right" class="scenario-flow-handle !h-full !w-4" connectable-start connectable-end />
</div>
</template>

<style>@import './connector.css';</style>
