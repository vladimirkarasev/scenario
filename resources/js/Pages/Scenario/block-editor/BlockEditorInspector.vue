<script setup>
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Separator } from '@/components/ui/separator'
import { Textarea } from '@/components/ui/textarea'
import ScenarioDateTimeDefaultInput from '@/modules/scenario/components/block-editor/block-fields/ScenarioDateTimeDefaultInput.vue'
import { Check, ChevronDown, ChevronUp, Copy, Info, Plus, Trash2 } from 'lucide-vue-next'

defineProps({
    selectedField: { type: Object, default: null },
    selectedFieldIndex: { type: Number, default: -1 },
    fieldsCount: { type: Number, default: 0 },
    canManageCatalog: { type: Boolean, default: false },
    blockId: { type: String, required: true },
    copiedInspectorFieldId: { type: String, default: null },
    fieldTypeLabel: { type: Function, required: true },
    fieldTypeIcon: { type: Function, required: true },
})

defineEmits([
    'move-up', 'move-down',
    'update-field', 'remove-field',
    'copy-field-var',
    'add-option', 'update-option', 'remove-option',
])
</script>

<template>
    <aside class="flex w-56 shrink-0 flex-col overflow-hidden border-l border-slate-200 bg-white">
        <div class="flex shrink-0 items-center gap-2 border-b border-slate-100 px-4 py-3 min-h-[48px]">
            <template v-if="selectedField">
                <component :is="fieldTypeIcon(selectedField.type)" class="size-4 shrink-0 text-slate-500" />
                <span class="flex-1 text-sm font-semibold text-slate-900">{{ fieldTypeLabel(selectedField.type) }}</span>
                <div class="flex items-center gap-0.5">
                    <Button
                        variant="ghost" size="icon" class="size-7"
                        :disabled="selectedFieldIndex === 0 || !canManageCatalog"
                        @click="$emit('move-up')"
                    >
                        <ChevronUp class="size-3.5" />
                    </Button>
                    <Button
                        variant="ghost" size="icon" class="size-7"
                        :disabled="selectedFieldIndex === fieldsCount - 1 || !canManageCatalog"
                        @click="$emit('move-down')"
                    >
                        <ChevronDown class="size-3.5" />
                    </Button>
                </div>
            </template>
            <template v-else>
                <Info class="size-4 shrink-0 text-slate-400" />
                <span class="text-sm font-semibold text-slate-900">Блок</span>
            </template>
        </div>

        <div class="flex-1 overflow-y-auto">
            <template v-if="selectedField">
                <div class="space-y-3 p-4">
                    <div v-if="selectedField.type !== 'hidden'" class="space-y-1.5">
                        <Label class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide">Название</Label>
                        <Input
                            :model-value="selectedField.label"
                            class="h-8 text-sm"
                            :disabled="!canManageCatalog"
                            @update:model-value="$emit('update-field', selectedField, { label: $event })"
                        />
                    </div>

                    <div v-if="selectedField.type !== 'collapse'" class="space-y-1.5">
                        <Label class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide">Ключ поля</Label>
                        <Input
                            :model-value="selectedField.name"
                            class="h-8 font-mono text-sm"
                            :disabled="!canManageCatalog"
                            @update:model-value="$emit('update-field', selectedField, { name: $event })"
                        />
                        <button
                            v-if="selectedField.name"
                            type="button"
                            class="flex w-full items-center gap-1.5 rounded-lg bg-slate-50 px-2 py-1.5 transition hover:bg-slate-100"
                            @click="$emit('copy-field-var', selectedField)"
                        >
                            <code class="flex-1 truncate font-mono text-[10px] text-slate-600">{{ blockId }}.{{ selectedField.name }}</code>
                            <Check v-if="copiedInspectorFieldId === selectedField.id" class="size-3 shrink-0 text-emerald-500" />
                            <Copy v-else class="size-3 shrink-0 text-slate-400" />
                        </button>
                    </div>

                    <label
                        v-if="!['rich_text', 'collapse', 'hidden'].includes(selectedField.type)"
                        class="flex h-9 items-center gap-2.5 rounded-xl border border-slate-200 bg-slate-50 px-3"
                    >
                        <input
                            :checked="Boolean(selectedField.required)"
                            type="checkbox"
                            class="size-3.5 rounded border-slate-300"
                            :disabled="!canManageCatalog"
                            @change="$emit('update-field', selectedField, { required: $event.target.checked })"
                        />
                        <span class="text-xs text-slate-700">Обязательное поле</span>
                    </label>

                    <Separator />

                    <template v-if="selectedField.type === 'input'">
                        <div class="space-y-1.5">
                            <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Placeholder</Label>
                            <Input :model-value="selectedField.placeholder" class="h-8 text-sm" :disabled="!canManageCatalog" @update:model-value="$emit('update-field', selectedField, { placeholder: $event })" />
                        </div>
                        <div class="space-y-1.5">
                            <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">По умолчанию</Label>
                            <Input :model-value="selectedField.value" class="h-8 text-sm" :disabled="!canManageCatalog" @update:model-value="$emit('update-field', selectedField, { value: $event })" />
                        </div>
                    </template>

                    <template v-else-if="selectedField.type === 'textarea'">
                        <div class="space-y-1.5">
                            <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Placeholder</Label>
                            <Input :model-value="selectedField.placeholder" class="h-8 text-sm" :disabled="!canManageCatalog" @update:model-value="$emit('update-field', selectedField, { placeholder: $event })" />
                        </div>
                        <div class="space-y-1.5">
                            <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Строк</Label>
                            <Input :model-value="String(selectedField.rows ?? 4)" type="number" min="2" class="h-8 text-sm" :disabled="!canManageCatalog" @update:model-value="$emit('update-field', selectedField, { rows: Number($event || 4) })" />
                        </div>
                        <div class="space-y-1.5">
                            <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">По умолчанию</Label>
                            <Textarea :model-value="selectedField.value" rows="3" :disabled="!canManageCatalog" @update:model-value="$emit('update-field', selectedField, { value: $event })" />
                        </div>
                    </template>

                    <template v-else-if="selectedField.type === 'number'">
                        <div class="space-y-1.5">
                            <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Placeholder</Label>
                            <Input :model-value="selectedField.placeholder" class="h-8 text-sm" :disabled="!canManageCatalog" @update:model-value="$emit('update-field', selectedField, { placeholder: $event })" />
                        </div>
                        <div class="space-y-1.5">
                            <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">По умолчанию</Label>
                            <Input
                                :model-value="selectedField.value ?? ''"
                                type="number"
                                class="h-8 text-sm"
                                :disabled="!canManageCatalog"
                                @update:model-value="$emit('update-field', selectedField, { value: $event === '' ? null : Number($event) })"
                            />
                        </div>
                    </template>

                    <template v-else-if="selectedField.type === 'hidden'">
                        <div class="space-y-1.5">
                            <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Значение</Label>
                            <Input
                                :model-value="selectedField.value"
                                placeholder="Значение скрытого поля"
                                class="h-8 text-sm"
                                :disabled="!canManageCatalog"
                                @update:model-value="$emit('update-field', selectedField, { value: $event })"
                            />
                        </div>
                    </template>

                    <template v-else-if="selectedField.type === 'collapse'">
                        <label class="flex h-9 items-center gap-2.5 rounded-xl border border-slate-200 bg-slate-50 px-3">
                            <input
                                :checked="Boolean(selectedField.defaultCollapsed)"
                                type="checkbox"
                                class="size-3.5 rounded border-slate-300"
                                :disabled="!canManageCatalog"
                                @change="$emit('update-field', selectedField, { defaultCollapsed: $event.target.checked })"
                            />
                            <span class="text-xs text-slate-700">Свёрнут по умолчанию</span>
                        </label>
                    </template>

                    <template v-else-if="selectedField.type === 'select'">
                        <div class="space-y-1.5">
                            <label class="flex h-9 items-center gap-2.5 rounded-xl border border-slate-200 bg-slate-50 px-3">
                                <input
                                    :checked="Boolean(selectedField.multiple)"
                                    type="checkbox"
                                    class="size-3.5 rounded border-slate-300"
                                    :disabled="!canManageCatalog"
                                    @change="$emit('update-field', selectedField, { multiple: $event.target.checked, value: $event.target.checked ? [] : '' })"
                                />
                                <span class="text-xs text-slate-700">Мультивыбор</span>
                            </label>
                            <label class="flex h-9 items-center gap-2.5 rounded-xl border border-slate-200 bg-slate-50 px-3">
                                <input
                                    :checked="Boolean(selectedField.allowRootSelection)"
                                    type="checkbox"
                                    class="size-3.5 rounded border-slate-300"
                                    :disabled="!canManageCatalog"
                                    @change="$emit('update-field', selectedField, { allowRootSelection: $event.target.checked })"
                                />
                                <span class="text-xs text-slate-700">Можно выбрать корень</span>
                            </label>
                        </div>

                        <div class="space-y-1.5">
                            <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">По умолчанию</Label>
                            <Input
                                :model-value="Array.isArray(selectedField.value) ? selectedField.value.join(', ') : selectedField.value"
                                placeholder="value или value1, value2"
                                class="h-8 text-sm"
                                :disabled="!canManageCatalog"
                                @update:model-value="$emit('update-field', selectedField, { value: selectedField.multiple ? String($event).split(',').map((i) => i.trim()).filter(Boolean) : $event })"
                            />
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Варианты</Label>
                                <Button type="button" variant="outline" size="sm" class="h-7 gap-1 px-2 text-xs" :disabled="!canManageCatalog" @click="$emit('add-option', selectedField)">
                                    <Plus class="size-3" />
                                    Добавить
                                </Button>
                            </div>
                            <div class="space-y-2">
                                <div
                                    v-for="option in selectedField.options"
                                    :key="option.id"
                                    class="space-y-1.5 rounded-xl border border-slate-200 bg-slate-50 p-2"
                                >
                                    <Input :model-value="option.label" placeholder="Название" class="h-7 text-xs" :disabled="!canManageCatalog" @update:model-value="$emit('update-option', selectedField, option.id, { label: $event })" />
                                    <Input :model-value="option.value" placeholder="value" class="h-7 font-mono text-xs" :disabled="!canManageCatalog" @update:model-value="$emit('update-option', selectedField, option.id, { value: $event })" />
                                    <div class="flex items-center gap-1.5">
                                        <select
                                            :value="option.parentId ?? ''"
                                            class="flex h-7 flex-1 rounded-lg border border-slate-200 bg-white px-2 text-xs outline-none"
                                            :disabled="!canManageCatalog"
                                            @change="$emit('update-option', selectedField, option.id, { parentId: $event.target.value })"
                                        >
                                            <option value="">Корень</option>
                                            <option
                                                v-for="parentOption in selectedField.options.filter((i) => i.id !== option.id)"
                                                :key="parentOption.id"
                                                :value="parentOption.id"
                                            >{{ parentOption.label }}</option>
                                        </select>
                                        <Button
                                            type="button" variant="ghost" size="icon" class="size-7 text-destructive hover:bg-destructive/10"
                                            :disabled="selectedField.options.length <= 1 || !canManageCatalog"
                                            @click="$emit('remove-option', selectedField, option.id)"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <template v-else-if="selectedField.type === 'datetime'">
                        <ScenarioDateTimeDefaultInput
                            :model-value="selectedField.value"
                            :mode="selectedField.defaultMode"
                            @update:model-value="$emit('update-field', selectedField, { value: $event })"
                            @update:mode="$emit('update-field', selectedField, { defaultMode: $event })"
                        />
                        <div class="space-y-1.5">
                            <Label class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Формат</Label>
                            <Input :model-value="selectedField.format" placeholder="DD.MM.YYYY HH:mm" class="h-8 text-sm" :disabled="!canManageCatalog" @update:model-value="$emit('update-field', selectedField, { format: $event })" />
                        </div>
                    </template>
                </div>
            </template>

            <template v-else>
                <div class="space-y-4 p-4">
                    <div class="space-y-1.5">
                        <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Block ID</div>
                        <code class="block rounded-lg bg-slate-50 px-3 py-2 font-mono text-xs text-slate-600">{{ blockId }}</code>
                    </div>
                    <div class="space-y-1.5">
                        <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Полей</div>
                        <div class="text-sm font-semibold text-slate-800">{{ fieldsCount }}</div>
                    </div>

                    <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-3 py-3 text-xs leading-5 text-slate-400">
                        Выбери поле, чтобы увидеть его настройки
                    </div>
                </div>
            </template>
        </div>

        <div v-if="selectedField && canManageCatalog" class="shrink-0 border-t border-slate-100 p-3">
            <Button
                variant="destructive"
                size="sm"
                class="w-full gap-2"
                @click="$emit('remove-field', selectedField.id)"
            >
                <Trash2 class="size-3.5" />
                Удалить поле
            </Button>
        </div>
    </aside>
</template>
