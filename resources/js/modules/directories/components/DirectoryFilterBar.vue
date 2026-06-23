<script setup lang="ts">
import { Input } from '@/components/ui/input'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import DatePickerFilter from '@/components/ui/date-picker/DatePickerFilter.vue'
import { Check, Plus, Search, X } from 'lucide-vue-next'
import type { useDirectoryItems } from '@/modules/directories/composables/useDirectoryItems'
import type { DirectorySchemaField } from '@/modules/directories/types/directory'

defineProps<{
    // Разделяемое состояние/хелперы списка справочника.
    ctx: ReturnType<typeof useDirectoryItems>
    // Показывать поле поиска (есть ли searchable-поля в схеме).
    searchable: boolean
    // Поля, для которых рендерим чипы фильтров.
    filterFields: DirectorySchemaField[]
}>()

// Эмитим при открытии popover'а фильтра — потребитель может лениво подгрузить
// варианты (см. SurveyDirectoryTableField → ensureBaseItems).
const emit = defineEmits<{ filterOpen: [field: DirectorySchemaField] }>()

function onPopoverChange(isOpen: boolean, field: DirectorySchemaField): void {
    if (isOpen) emit('filterOpen', field)
}
</script>

<template>
    <!--
      `ctx` — разделяемый реактивный стор useDirectoryItems, а не value-prop.
      Чтение/запись его фильтров здесь — намеренный дизайн общего фильтр-бара.
    -->
    <!-- eslint-disable vue/no-mutating-props -->
    <!-- Search bar -->
    <div v-if="searchable" class="relative">
        <Search class="pointer-events-none absolute left-3 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
        <input
            v-model="ctx.searchQuery.value"
            type="search"
            placeholder="Поиск по записям..."
            class="h-9 w-full rounded-md border border-input bg-background pl-8 pr-3 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring"
        />
    </div>

    <!-- Filter chips -->
    <div v-if="filterFields.length" class="flex flex-wrap items-center gap-1.5">
        <template v-for="f in filterFields" :key="f.key">
            <Popover @update:open="(o: boolean) => onPopoverChange(o, f)">
                <PopoverTrigger as-child>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs transition-colors"
                        :class="ctx.isFilterActive(f)
                            ? 'border-primary/40 bg-primary/10 text-primary hover:bg-primary/15'
                            : 'border-dashed border-border/60 text-muted-foreground hover:border-border hover:text-foreground'"
                    >
                        <Plus v-if="!ctx.isFilterActive(f)" class="size-3 shrink-0" />
                        <span :class="ctx.isFilterActive(f) ? 'font-medium' : ''">{{ f.name }}</span>
                        <template v-if="ctx.isFilterActive(f)">
                            <span class="max-w-[120px] truncate text-primary/80">: {{ ctx.getFilterDisplayValue(f) }}</span>
                            <span class="ml-0.5 flex items-center opacity-60 hover:opacity-100" @click.stop="ctx.clearFilter(f)">
                                <X class="size-3" />
                            </span>
                        </template>
                    </button>
                </PopoverTrigger>

                <PopoverContent align="start" :side-offset="4" class="w-64 p-3" @open-auto-focus.prevent>
                    <div class="space-y-2">
                        <p class="text-xs font-medium">{{ f.name }}</p>

                        <!-- boolean -->
                        <select
                            v-if="f.filter_type === 'boolean'"
                            v-model="ctx.activeFilters[f.key]"
                            class="h-8 w-full rounded-md border border-input bg-background px-2 text-xs"
                        >
                            <option value="">Все</option>
                            <option value="true">Да</option>
                            <option value="false">Нет</option>
                        </select>

                        <!-- list multi -->
                        <div v-else-if="f.filter_type === 'list' && f.filter_multiple" class="max-h-48 space-y-0.5 overflow-y-auto">
                            <p v-if="ctx.listOptions(f).length === 0" class="py-2 text-center text-xs text-muted-foreground">Нет вариантов</p>
                            <button
                                v-for="opt in ctx.listOptions(f)"
                                :key="opt"
                                type="button"
                                class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-xs hover:bg-accent"
                                @click="ctx.toggleMultiFilter(f.key, opt)"
                            >
                                <span
                                    class="flex size-3.5 shrink-0 items-center justify-center rounded border"
                                    :class="(ctx.activeFiltersMulti[f.key] ?? []).includes(opt) ? 'border-primary bg-primary text-primary-foreground' : 'border-border'"
                                >
                                    <Check v-if="(ctx.activeFiltersMulti[f.key] ?? []).includes(opt)" class="size-2.5" />
                                </span>
                                <span class="flex-1 truncate text-left">{{ opt }}</span>
                            </button>
                        </div>

                        <!-- list single -->
                        <div v-else-if="f.filter_type === 'list'" class="max-h-48 space-y-0.5 overflow-y-auto">
                            <button
                                type="button"
                                class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-xs text-muted-foreground hover:bg-accent"
                                @click="ctx.activeFilters[f.key] = ''"
                            >
                                <span class="flex-1 text-left">Все</span>
                                <Check v-if="!ctx.activeFilters[f.key]" class="size-3 shrink-0 text-primary" />
                            </button>
                            <button
                                v-for="opt in ctx.listOptions(f)"
                                :key="opt"
                                type="button"
                                class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-xs hover:bg-accent"
                                :class="ctx.activeFilters[f.key] === opt ? 'font-medium text-foreground' : 'text-foreground/80'"
                                @click="ctx.activeFilters[f.key] = ctx.activeFilters[f.key] === opt ? '' : opt"
                            >
                                <span class="flex-1 truncate text-left">{{ opt }}</span>
                                <Check v-if="ctx.activeFilters[f.key] === opt" class="size-3 shrink-0 text-primary" />
                            </button>
                        </div>

                        <!-- between date/datetime -->
                        <template v-else-if="f.filter_operator === 'between' && (f.filter_type === 'date' || f.filter_type === 'datetime')">
                            <div class="space-y-1.5">
                                <DatePickerFilter
                                    v-model="ctx.activeFilters[f.key]"
                                    :show-time="f.filter_type === 'datetime'"
                                    :placeholder="f.filter_placeholder ?? 'От'"
                                />
                                <DatePickerFilter
                                    v-model="ctx.activeFiltersTo[f.key]"
                                    :show-time="f.filter_type === 'datetime'"
                                    placeholder="До"
                                />
                            </div>
                        </template>

                        <!-- between numeric -->
                        <template v-else-if="f.filter_operator === 'between'">
                            <div class="space-y-1.5">
                                <Input v-model="ctx.activeFilters[f.key]" type="number" :placeholder="f.filter_placeholder ?? 'От'" class="h-7 text-xs" />
                                <Input v-model="ctx.activeFiltersTo[f.key]" type="number" placeholder="До" class="h-7 text-xs" />
                            </div>
                        </template>

                        <!-- date -->
                        <DatePickerFilter
                            v-else-if="f.filter_type === 'date'"
                            v-model="ctx.activeFilters[f.key]"
                            :placeholder="f.filter_placeholder ?? 'Дата...'"
                        />

                        <!-- datetime -->
                        <DatePickerFilter
                            v-else-if="f.filter_type === 'datetime'"
                            v-model="ctx.activeFilters[f.key]"
                            show-time
                            placeholder="Дата и время..."
                        />

                        <!-- integer -->
                        <Input
                            v-else-if="f.filter_type === 'integer'"
                            v-model="ctx.activeFilters[f.key]"
                            type="number"
                            step="1"
                            :placeholder="f.filter_placeholder ?? 'Число...'"
                            class="h-7 text-xs"
                        />

                        <!-- text / default -->
                        <Input
                            v-else
                            v-model="ctx.activeFilters[f.key]"
                            :placeholder="f.filter_placeholder ?? 'Поиск...'"
                            class="h-7 text-xs"
                        />
                    </div>
                </PopoverContent>
            </Popover>
        </template>

        <button
            v-if="ctx.hasActiveFilters.value"
            type="button"
            class="text-xs text-muted-foreground transition-colors hover:text-foreground"
            @click="ctx.clearFilters()"
        >
            Сбросить все
        </button>
    </div>
</template>
