<script setup lang="ts">
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Check, Copy, TriangleAlert } from 'lucide-vue-next'
import type { BlockField } from '../../../lib/scenario-block-fields'

type FieldNamePatch = Partial<Pick<BlockField, 'label' | 'name' | 'varName'>>

defineProps<{
    field: BlockField
    disabled?: boolean
    isVarNameUnique: boolean
    isCopied: boolean
}>()
const emit = defineEmits<{
    update: [patch: FieldNamePatch]
    'copy-var-name': []
}>()
defineOptions({ inheritAttrs: false })
</script>

<template>
    <div v-if="field.type !== 'hidden'" class="space-y-1.5">
        <Label class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide">Название</Label>
        <Input
            :model-value="field.label"
            class="h-8 text-sm"
            :disabled="disabled"
            @update:model-value="emit('update', { label: $event })"
        />
    </div>

    <div v-if="field.type !== 'collapse'" class="space-y-1.5">
        <Label class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide">Ключ поля</Label>
        <Input
            :model-value="field.name"
            class="h-8 font-mono text-sm"
            :disabled="disabled"
            @update:model-value="emit('update', { name: $event.replace(/\s+/g, '_') })"
        />
    </div>

    <div v-if="field.type !== 'collapse' && field.type !== 'rich_text'" class="space-y-1.5">
        <Label class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide">Название переменной</Label>
        <Input
            :model-value="field.varName"
            class="h-8 font-mono text-sm"
            :disabled="disabled"
            @update:model-value="emit('update', { varName: $event.replace(/\s+/g, '_') })"
        />
        <div v-if="!isVarNameUnique" class="flex items-start gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-2">
            <TriangleAlert class="mt-px size-3 shrink-0 text-amber-500" />
            <p class="text-[11px] text-amber-700">Переменная с таким именем уже существует — возможны конфликты</p>
        </div>
        <button
            v-if="field.varName"
            type="button"
            class="flex w-full items-center gap-1.5 rounded-lg bg-slate-50 px-2 py-1.5 transition hover:bg-slate-100"
            @click="emit('copy-var-name')"
        >
            <code class="flex-1 truncate font-mono text-[10px] text-slate-600">&#123;&#123; {{ field.varName }} &#125;&#125;</code>
            <Check v-if="isCopied" class="size-3 shrink-0 text-emerald-500" />
            <Copy v-else class="size-3 shrink-0 text-slate-400" />
        </button>
    </div>
</template>
