<script setup lang="ts">
import {ChevronsUpDown, X} from 'lucide-vue-next'
import SelectionChip from '@/components/SelectionChip.vue'

interface Chip {
  value: string;
  label: string
}

defineProps<{
  chips: Chip[]
  hasSelection: boolean
  directoryId: string
  disabled?: boolean
  error?: boolean
}>()

const emit = defineEmits<{
  open: []
  removeChip: [value: string]
  clearSelection: [event: MouseEvent]
}>()
</script>

<template>
  <button
      type="button"
      :disabled="disabled || !directoryId"
      :title="!directoryId ? 'Справочник не задан' : undefined"
      class="flex min-h-9 w-full items-start gap-2 rounded-xl border px-3 py-1.5 text-left text-sm transition disabled:cursor-not-allowed disabled:opacity-50"
      :class="[error ? 'border-destructive' : 'border-input hover:border-slate-300']"
      @click="emit('open')"
  >
    <div class="flex min-w-0 flex-1 flex-wrap items-start gap-1">
      <span v-if="!directoryId" class="text-amber-600">Справочник не задан</span>
      <template v-else-if="hasSelection">
                <SelectionChip
                    v-for="chip in chips"
                    :key="chip.value"
                    :label="chip.label"
                    :removable="!disabled"
                    @remove="emit('removeChip', chip.value)"
                />
      </template>
      <span v-else class="text-muted-foreground">Выберите из таблицы...</span>
    </div>
    <span class="flex shrink-0 items-center gap-1 self-start pt-0.5">
            <X
                v-if="hasSelection && !disabled"
                class="size-3.5 text-muted-foreground transition hover:text-foreground"
                @click="(e: MouseEvent) => emit('clearSelection', e)"
            />
            <ChevronsUpDown class="size-3.5 text-muted-foreground"/>
        </span>
  </button>
</template>
