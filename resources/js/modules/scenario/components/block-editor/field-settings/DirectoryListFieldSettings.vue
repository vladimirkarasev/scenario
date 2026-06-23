<script lang="ts">
import { markRaw } from 'vue'
import { List } from 'lucide-vue-next'
export const fieldMeta = { type: 'directory_list', label: 'Список', icon: markRaw(List) }
</script>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Input } from '@/components/ui/input'
import { ChevronsUpDown, X } from 'lucide-vue-next'
import DirectoryPickerDialog from '@/modules/scenario/components/pickers/DirectoryPickerDialog.vue'
import DirectoryLabelTemplateField from './DirectoryLabelTemplateField.vue'
import { useDirectorySchemaLoader } from '@/modules/directories/composables/useDirectorySchemaLoader'
import type { Directory } from '@/modules/directories/types/directory'
import type { DirectoryListBlockField } from '../../../lib/scenario-block-fields'

const props = defineProps<{ field: DirectoryListBlockField; disabled?: boolean }>()
const emit = defineEmits<{ update: [patch: Partial<DirectoryListBlockField>] }>()
defineOptions({ inheritAttrs: false })
const pickerOpen = ref(false)

const { directoryName, versions, schemaFields, versionLabel } = useDirectorySchemaLoader(
    computed(() => props.field.directoryId),
    computed(() => props.field.versionId),
    (id) => emit('update', { versionId: id }),
)

function onVersionChange(versionId: string): void {
    emit('update', { versionId, labelTemplate: '' })
}

function onDirectorySelect(directory: Directory): void {
    directoryName.value = directory.name
    const activeId = directory.active_version ? String(directory.active_version.id) : ''
    emit('update', { directoryId: directory.id, versionId: activeId, labelTemplate: '' })
}

function clearDirectory(): void {
    emit('update', { directoryId: '', versionId: '', labelTemplate: '' })
}
</script>

<template>
    <div class="space-y-3">
        <!-- Directory picker -->
        <div class="space-y-1.5">
            <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">Справочник</label>
            <div class="flex items-center gap-1.5">
                <button
                    type="button"
                    class="flex h-8 flex-1 items-center justify-between gap-2 rounded-lg border border-slate-200 bg-white px-2.5 text-xs transition hover:border-slate-300 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="disabled"
                    @click="pickerOpen = true"
                >
                    <span :class="field.directoryId ? 'text-slate-800' : 'text-slate-400'">
                        {{ field.directoryId ? (directoryName || 'Загрузка...') : '— не выбрано —' }}
                    </span>
                    <ChevronsUpDown class="size-3 shrink-0 text-slate-400" />
                </button>
                <button
                    v-if="field.directoryId && !disabled"
                    type="button"
                    class="flex size-8 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-400 transition hover:border-slate-300 hover:text-slate-600"
                    @click="clearDirectory"
                >
                    <X class="size-3.5" />
                </button>
            </div>
        </div>

        <!-- Version selector -->
        <div v-if="field.directoryId && versions.length" class="space-y-1.5">
            <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">Версия</label>
            <Select
                :model-value="field.versionId"
                :disabled="disabled"
                @update:model-value="onVersionChange($event)"
            >
                <SelectTrigger class="w-full rounded-xl border-slate-200 text-xs" size="sm">
                    <SelectValue placeholder="Загрузка..." />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="v in versions" :key="v.id" :value="String(v.id)">
                        {{ versionLabel(v) }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <!-- Label template -->
        <DirectoryLabelTemplateField
            v-if="field.directoryId"
            :model-value="field.labelTemplate"
            :fields="schemaFields"
            :disabled="disabled"
            @update:model-value="emit('update', { labelTemplate: $event })"
        />

        <div class="space-y-1.5">
            <label class="flex h-9 items-center gap-2.5 rounded-xl border border-slate-200 bg-slate-50 px-3">
                <input
                    :checked="Boolean(field.multiple)"
                    type="checkbox"
                    class="size-3.5 rounded border-slate-300"
                    :disabled="disabled"
                    @change="emit('update', { multiple: ($event.target as HTMLInputElement).checked })"
                />
                <span class="text-xs text-slate-700">Мультивыбор</span>
            </label>
            <label class="flex h-9 items-center gap-2.5 rounded-xl border border-slate-200 bg-slate-50 px-3">
                <input
                    :checked="Boolean(field.allowRootSelection)"
                    type="checkbox"
                    class="size-3.5 rounded border-slate-300"
                    :disabled="disabled"
                    @change="emit('update', { allowRootSelection: ($event.target as HTMLInputElement).checked })"
                />
                <span class="text-xs text-slate-700">Можно выбрать корень</span>
            </label>
        </div>

        <div class="space-y-1.5">
            <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">Поиск по умолчанию</label>
            <Input
                :model-value="field.defaultSearch"
                :disabled="disabled"
                placeholder="Значение для поиска при загрузке"
                class="h-8 text-sm"
                @update:model-value="emit('update', { defaultSearch: String($event) })"
            />
        </div>
    </div>

    <DirectoryPickerDialog
        :open="pickerOpen"
        :selected-id="field.directoryId || undefined"
        @update:open="pickerOpen = $event"
        @select="onDirectorySelect"
    />
</template>
