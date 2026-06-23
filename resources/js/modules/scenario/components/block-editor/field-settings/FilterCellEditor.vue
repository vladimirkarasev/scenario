<script setup lang="ts">
import { computed } from 'vue'
import DatePicker from '@/components/ui/date-picker/DatePicker.vue'
import FilterModeToggle from './FilterModeToggle.vue'
import FilterListMultiPicker from './FilterListMultiPicker.vue'
import type { DirectorySchemaField } from '@/modules/directories/types/directory'
import type { DirectoryTableFieldConfig } from '../../../lib/scenario-block-fields'

const props = defineProps<{
    schemaField: DirectorySchemaField
    cfg: DirectoryTableFieldConfig & { name?: string }
    options: string[]
    optionsLoaded: boolean
    disabled?: boolean
}>()

const emit = defineEmits<{ update: [patch: Partial<DirectoryTableFieldConfig>] }>()

const ft = computed(() => props.schemaField.filter_type)
const isListMulti = computed(() => ft.value === 'list' && props.schemaField.filter_multiple === true)
const isDateLike = computed(() => ft.value === 'date' || ft.value === 'datetime')
const isDateTime = computed(() => ft.value === 'datetime')
const isBoolean = computed(() => ft.value === 'boolean')
const isInteger = computed(() => ft.value === 'integer')

const mode = computed<'literal' | 'template'>(() => props.cfg.filterMode ?? 'literal')
const selectedValues = computed(() => props.cfg.filterValues ?? [])
const inputDisabled = computed(() => Boolean(props.disabled) || !props.cfg.filterable)
const disabledHint = computed(() => !props.cfg.filterable ? 'Сначала включите «Фильтр»' : '')

const inputClass = 'w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-[12px] text-slate-800 placeholder:text-slate-400 outline-none focus:border-slate-300 disabled:cursor-not-allowed disabled:opacity-40'
const monoInputClass = 'w-full rounded-lg border border-slate-200 bg-white px-2 py-1 font-mono text-[11px] text-slate-800 placeholder:text-slate-400 outline-none focus:border-slate-300 disabled:cursor-not-allowed disabled:opacity-40'

function setMode(m: 'literal' | 'template'): void {
    emit('update', { filterMode: m })
}

function setValue(v: string): void {
    emit('update', { defaultValue: v })
}

function toggleListValue(v: string): void {
    const current = selectedValues.value
    const next = current.includes(v) ? current.filter((x) => x !== v) : [...current, v]
    emit('update', { filterValues: next })
}

function addCustomListValue(v: string): void {
    if (selectedValues.value.includes(v)) return
    emit('update', { filterValues: [...selectedValues.value, v] })
}

function clearListValues(): void {
    emit('update', { filterValues: [] })
}
</script>

<template>
    <!-- list-multi: tag-picker как в самом справочнике (с поддержкой произвольных значений). -->
    <FilterListMultiPicker
        v-if="isListMulti"
        :selected="selectedValues"
        :options="options"
        :loaded="optionsLoaded"
        :disabled="inputDisabled"
        :disabled-hint="disabledHint"
        @toggle="toggleListValue"
        @clear="clearListValues"
        @add-custom="addCustomListValue"
    />

    <!-- date / datetime / boolean / integer / string: toggle Литерал/Шаблон + соответствующий ввод. -->
    <div v-else class="space-y-1">
        <FilterModeToggle
            :model-value="mode"
            :disabled="inputDisabled"
            :literal-label="isDateLike ? 'Дата' : isInteger ? 'Число' : isBoolean ? 'Значение' : 'Текст'"
            @update:model-value="setMode"
        />

        <template v-if="mode === 'literal'">
            <DatePicker
                v-if="isDateLike"
                :model-value="cfg.defaultValue"
                :show-time="isDateTime"
                :disabled="inputDisabled"
                clearable
                @update:model-value="(v: string) => setValue(v ?? '')"
            />
            <select
                v-else-if="isBoolean"
                :value="cfg.defaultValue"
                :disabled="inputDisabled"
                class="h-7 w-full rounded-lg border border-slate-200 bg-white px-2 text-[12px] text-slate-800 outline-none focus:border-slate-300 disabled:cursor-not-allowed disabled:opacity-40"
                @change="setValue(($event.target as HTMLSelectElement).value)"
            >
                <option value="">—</option>
                <option value="true">Да</option>
                <option value="false">Нет</option>
            </select>
            <input
                v-else-if="isInteger"
                :value="cfg.defaultValue"
                :disabled="inputDisabled"
                type="number"
                step="1"
                placeholder="—"
                :class="inputClass"
                @input="setValue(($event.target as HTMLInputElement).value)"
            />
            <input
                v-else
                :value="cfg.defaultValue"
                :disabled="inputDisabled"
                :title="disabledHint"
                type="text"
                placeholder="—"
                :class="inputClass"
                @input="setValue(($event.target as HTMLInputElement).value)"
            />
        </template>

        <input
            v-else
            :value="cfg.defaultValue"
            :disabled="inputDisabled"
            type="text"
            placeholder="{{ varName }}"
            :class="monoInputClass"
            @input="setValue(($event.target as HTMLInputElement).value)"
        />
    </div>
</template>
