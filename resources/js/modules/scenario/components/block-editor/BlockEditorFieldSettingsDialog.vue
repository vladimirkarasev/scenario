<script setup lang="ts">
import { computed, type Component } from 'vue'
import { Settings, Trash2 } from 'lucide-vue-next'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Separator } from '@/components/ui/separator'
import FieldNameSettings from '@/modules/scenario/components/block-editor/field-settings/FieldNameSettings.vue'
import ValidationChainBuilder from '@/modules/scenario/components/block-editor/field-settings/ValidationChainBuilder.vue'
import ScenarioVariableList from '@/modules/scenario/components/ScenarioVariableList.vue'
import { AVAILABLE_VALIDATION_RULES } from '@/modules/scenario/lib/scenario-block-fields'
import type { BlockField } from '@/modules/scenario/lib/scenario-block-fields'

interface Variable {
    fieldId: string
    blockId: string
    blockTitle: string
    varRef: string
    label: string
    isCurrent: boolean
}

interface BlockEntry {
    id: string
    type: string
    data: Record<string, unknown>
}

const props = defineProps<{
    open: boolean
    field: BlockField | null
    fieldIndex: number
    fieldCount: number
    fieldTypeLabel: string
    fieldTypeIcon: Component
    settingsComponent: Component | null
    canEdit: boolean
    isVarNameUnique: boolean
    isCopied: boolean
    blockTitle: string
    allVariables: Variable[]
    blocks: BlockEntry[]
    currentBlockId: string
    userVariables: { id: string; name: string; label: string }[]
}>()

const emit = defineEmits<{
    'update:open': [boolean]
    update: [Partial<BlockField>]
    addOption: []
    updateOption: [{ id: string; changes: Record<string, unknown> }]
    removeOption: [string]
    reorderOptions: [unknown[]]
    moveToIndex: [number]
    copyVarName: []
    delete: []
}>()

const requiredToggleVisible = computed(() =>
    !['rich_text', 'collapse', 'hidden'].includes(props.field?.type ?? ''),
)
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="flex flex-col !max-w-4xl max-h-[90vh] overflow-hidden p-0">
            <DialogHeader class="shrink-0 border-b border-slate-100 px-6 py-4">
                <DialogTitle class="flex items-center gap-2.5">
                    <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-slate-100">
                        <component :is="field ? fieldTypeIcon : Settings" class="size-3.5 text-slate-600" />
                    </div>
                    <span class="text-[15px] font-semibold text-slate-900">
                        {{ field ? fieldTypeLabel : 'Настройки поля' }}
                    </span>
                </DialogTitle>
                <DialogDescription class="sr-only">Настройки параметров поля блока сценария</DialogDescription>
            </DialogHeader>

            <div v-if="field" class="min-h-0 flex-1 overflow-hidden flex">
<!-- Variables column -->
                <div class="flex w-60 shrink-0 flex-col border-r border-slate-100">
                    <p class="shrink-0 border-b border-slate-100 px-4 py-3 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Переменные</p>
                    <div class="flex-1 overflow-y-auto p-3">
                        <ScenarioVariableList
                            :variables="allVariables"
                            :blocks="blocks"
                            :user-variables="userVariables"
                            :current-block-id="currentBlockId"
                            hover-class="hover:bg-slate-100"
                        />
                    </div>
                </div>

                <!-- Settings column -->
                <div class="min-w-0 flex-1 overflow-y-auto space-y-4 px-6 py-5">
                    <FieldNameSettings
                        :field="field"
                        :disabled="!canEdit"
                        :is-var-name-unique="isVarNameUnique"
                        :is-copied="isCopied"
                        @update="emit('update', $event)"
                        @copy-var-name="emit('copyVarName')"
                    />

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Позиция</Label>
                            <Input
                                :model-value="fieldIndex + 1"
                                type="number"
                                min="1"
                                :max="fieldCount"
                                class="h-9 text-sm"
                                :disabled="!canEdit"
                                @update:model-value="emit('moveToIndex', Number($event) - 1)"
                            />
                        </div>

                        <div v-if="requiredToggleVisible" class="flex items-end">
                            <label class="flex h-9 w-full cursor-pointer items-center gap-2.5 rounded-xl border border-slate-200 bg-slate-50 px-3 transition hover:bg-slate-100">
                                <input
                                    type="checkbox"
                                    :checked="Boolean(field.required)"
                                    class="size-3.5 rounded border-slate-300 accent-blue-600"
                                    :disabled="!canEdit"
                                    @change="emit('update', { required: ($event.target as HTMLInputElement).checked })"
                                />
                                <span class="text-[12px] text-slate-700">Обязательное поле</span>
                            </label>
                        </div>
                    </div>

                    <Separator class="bg-slate-100" />

                    <component
                        :is="settingsComponent"
                        :field="field"
                        :disabled="!canEdit"
                        @update="emit('update', $event)"
                        @add-option="emit('addOption')"
                        @update-option="emit('updateOption', $event)"
                        @remove-option="emit('removeOption', $event)"
                        @reorder-options="emit('reorderOptions', $event)"
                    />

                    <template v-if="field && AVAILABLE_VALIDATION_RULES[field.type]?.length">
                        <Separator class="bg-slate-100" />
                        <ValidationChainBuilder
                            :field="field"
                            :disabled="!canEdit"
                            @update="emit('update', $event)"
                        />
                    </template>
                </div>
            </div>

            <div v-if="canEdit && field" class="flex shrink-0 items-center justify-end gap-2 border-t border-slate-100 px-6 py-4">
                <button
                    type="button"
                    class="inline-flex h-8 items-center gap-1.5 rounded-lg px-3 text-[12px] font-medium text-red-500 ring-1 ring-red-200 transition hover:bg-red-50"
                    @click="emit('delete')"
                >
                    <Trash2 class="size-3.5" />
                    Удалить поле
                </button>
                <button
                    type="button"
                    class="inline-flex h-8 items-center gap-1.5 rounded-lg bg-blue-600 px-4 text-[12px] font-semibold text-white transition hover:bg-blue-700"
                    @click="emit('update:open', false)"
                >
                    Готово
                </button>
            </div>
        </DialogContent>
    </Dialog>
</template>
