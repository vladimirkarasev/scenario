<script setup>
import {
    Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import ConditionRenderer from '@/modules/scenario/components/player/ConditionRenderer.vue'
import { Check, Copy, Settings } from 'lucide-vue-next'
import { LOGICAL_VARIABLES } from '@/modules/scenario/lib/scenario-flow-constants'

defineProps({
    open: { type: Boolean, default: false },
    selectedEdge: { type: Object, default: null },
    selectedEdgeFromCondition: { type: Boolean, default: false },
    conditionPreviewQuestion: { type: String, default: '' },
    conditionPreviewOptions: { type: Array, default: () => [] },
    editable: { type: Boolean, default: false },
    copiedConditionVariableId: { type: String, default: null },
})

defineEmits(['update:open', 'apply-logical', 'update-edge'])
</script>

<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <DialogContent class="sm:max-w-3xl">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Settings class="size-4" />
                    <span>Настройки условия</span>
                </DialogTitle>
                <DialogDescription class="sr-only">Настройки условия перехода между блоками</DialogDescription>
            </DialogHeader>

            <div v-if="selectedEdgeFromCondition" class="grid gap-5 py-2 md:grid-cols-[240px_minmax(0,1fr)]">
                <aside class="space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Логические переменные</div>
                    <div class="space-y-0.5">
                        <button
                            v-for="variable in LOGICAL_VARIABLES"
                            :key="variable.id"
                            type="button"
                            class="flex w-full items-center justify-between gap-2 rounded-md px-2 py-1 text-left transition hover:bg-white"
                            @click="$emit('apply-logical', variable)"
                        >
                            <span class="truncate font-mono text-[10px] text-slate-500">{{ variable.value }}</span>
                            <Check v-if="copiedConditionVariableId === variable.id" class="size-3 shrink-0 text-emerald-500" />
                            <Copy v-else class="size-3 shrink-0 text-slate-300" />
                        </button>
                    </div>
                </aside>

                <div class="space-y-4">
                    <div class="space-y-2">
                        <Label for="condition-value">Значение</Label>
                        <Input
                            id="condition-value"
                            :model-value="selectedEdge?.data?.value ?? ''"
                            class="border-slate-200"
                            placeholder="Например: user@example.test"
                            :disabled="!editable"
                            @update:model-value="$emit('update-edge', { key: 'value', value: $event })"
                        />
                    </div>
                </div>
            </div>

            <div v-if="selectedEdgeFromCondition" class="border-t border-slate-100 pt-1">
                <div class="mb-3 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Предпросмотр</div>
                <ConditionRenderer
                    :question="conditionPreviewQuestion"
                    :options="conditionPreviewOptions"
                />
            </div>

            <DialogFooter>
                <Button type="button" @click="$emit('update:open', false)">Готово</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
