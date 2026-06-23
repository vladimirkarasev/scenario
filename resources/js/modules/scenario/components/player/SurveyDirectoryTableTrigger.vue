<script setup lang="ts">
import { ChevronsUpDown, X } from 'lucide-vue-next'

interface Chip { value: string; label: string }

defineProps<{
    chips: Chip[]
    hasSelection: boolean
    directoryId: string
    disabled?: boolean
    error?: boolean
}>()

const emit = defineEmits<{
    open: []
    removeChip: [value: string, event: MouseEvent]
    clearSelection: [event: MouseEvent]
}>()
</script>

<template>
    <button
        type="button"
        :disabled="disabled || !directoryId"
        :title="!directoryId ? 'Справочник не задан' : undefined"
        class="flex min-h-9 w-full items-center gap-2 rounded-xl border px-3 py-1.5 text-left text-sm transition disabled:cursor-not-allowed disabled:opacity-50"
        :class="[error ? 'border-destructive' : 'border-input hover:border-slate-300']"
        @click="emit('open')"
    >
        <div class="flex min-w-0 flex-1 flex-wrap gap-1">
            <span v-if="!directoryId" class="text-amber-600">Справочник не задан</span>
            <template v-else-if="hasSelection">
                <span
                    v-for="chip in chips"
                    :key="chip.value"
                    :title="chip.label"
                    class="inline-flex items-center gap-1 rounded-md bg-slate-100 py-0.5 pl-2 pr-1 text-xs font-medium text-slate-700"
                >
                    <span class="max-w-[160px] truncate">{{ chip.label }}</span>
                    <button
                        v-if="!disabled"
                        type="button"
                        title="Удалить"
                        class="inline-flex size-3.5 shrink-0 items-center justify-center rounded text-slate-400 transition hover:bg-slate-200 hover:text-slate-700"
                        @click.stop="emit('removeChip', chip.value, $event)"
                    >
                        <X class="size-3" />
                    </button>
                </span>
            </template>
            <span v-else class="text-muted-foreground">Выберите из таблицы...</span>
        </div>
        <span class="flex shrink-0 items-center gap-1 self-center">
            <X
                v-if="hasSelection && !disabled"
                class="size-3.5 text-muted-foreground transition hover:text-foreground"
                @click="(e: MouseEvent) => emit('clearSelection', e)"
            />
            <ChevronsUpDown class="size-3.5 text-muted-foreground" />
        </span>
    </button>
</template>
