<script setup lang="ts">
import { computed, ref } from 'vue'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { Search, Check, X, Plus } from 'lucide-vue-next'

const props = defineProps<{
    selected: string[]
    options: string[]
    loaded: boolean
    disabled?: boolean
    disabledHint?: string
}>()

const emit = defineEmits<{
    toggle: [value: string]
    clear: []
    addCustom: [value: string]
}>()

const open = ref(false)
const search = ref('')

const filteredOptions = computed(() => {
    const q = search.value.trim().toLowerCase()
    if (!q) return props.options
    return props.options.filter((o) => o.toLowerCase().includes(q))
})

function commitCustom(): void {
    const v = search.value.trim()
    if (!v) return
    if (props.options.includes(v)) {
        if (!props.selected.includes(v)) emit('toggle', v)
    } else {
        emit('addCustom', v)
    }
    search.value = ''
}
</script>

<template>
    <Popover :open="open" @update:open="open = $event">
        <PopoverTrigger as-child>
            <button
                type="button"
                :disabled="disabled"
                :title="disabled ? disabledHint : ''"
                class="flex w-full min-h-[28px] flex-wrap items-center gap-1 rounded-lg border border-slate-200 bg-white px-2 py-1 text-left text-[11px] transition hover:border-slate-300 disabled:cursor-not-allowed disabled:opacity-40"
            >
                <template v-if="selected.length">
                    <span
                        v-for="val in selected"
                        :key="val"
                        class="inline-flex items-center gap-0.5 rounded-md bg-primary/10 px-1.5 py-0.5 text-[10px] font-medium text-primary"
                    >
                        <span class="max-w-[80px] truncate">{{ val }}</span>
                        <span
                            class="inline-flex size-2.5 shrink-0 items-center justify-center rounded text-primary/70 hover:text-primary"
                            @click.stop="emit('toggle', val)"
                        >
                            <X class="size-2" />
                        </span>
                    </span>
                </template>
                <span v-else class="text-slate-400">—</span>
            </button>
        </PopoverTrigger>
        <PopoverContent align="start" :side-offset="4" class="w-64 p-0" @open-auto-focus.prevent>
            <div class="border-b border-slate-100 p-2">
                <div class="relative">
                    <Search class="pointer-events-none absolute left-2 top-1/2 size-3 -translate-y-1/2 text-slate-300" />
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Поиск или ввести своё..."
                        class="h-7 w-full rounded-md border border-slate-200 bg-white pl-7 pr-2 text-[11px] focus:border-blue-400 focus:outline-none"
                        @keydown.enter.prevent="commitCustom"
                    />
                </div>
            </div>
            <div class="max-h-56 space-y-0.5 overflow-y-auto p-1">
                <p v-if="!loaded" class="px-2 py-2 text-center text-[11px] text-slate-400">Загрузка...</p>
                <template v-else>
                    <button
                        v-if="search.trim() && !filteredOptions.includes(search.trim())"
                        type="button"
                        class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-[11px] transition hover:bg-blue-50"
                        @click="commitCustom"
                    >
                        <Plus class="size-3 shrink-0 text-blue-500" />
                        <span class="flex-1 truncate text-blue-600">Добавить «{{ search }}»</span>
                    </button>
                    <p v-if="filteredOptions.length === 0 && !search.trim()" class="px-2 py-2 text-center text-[11px] text-slate-400">Опций нет</p>
                    <button
                        v-for="opt in filteredOptions"
                        :key="opt"
                        type="button"
                        class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-[11px] transition hover:bg-slate-50"
                        @click="emit('toggle', opt)"
                    >
                        <span
                            class="flex size-3.5 shrink-0 items-center justify-center rounded border"
                            :class="selected.includes(opt) ? 'border-primary bg-primary text-primary-foreground' : 'border-slate-300'"
                        >
                            <Check v-if="selected.includes(opt)" class="size-2.5" />
                        </span>
                        <span class="flex-1 truncate">{{ opt }}</span>
                    </button>
                </template>
            </div>
            <div v-if="selected.length > 0" class="flex items-center justify-between border-t border-slate-100 px-2 py-1.5 text-[10px]">
                <span class="text-slate-400">Выбрано: {{ selected.length }}</span>
                <button
                    type="button"
                    class="text-slate-400 transition hover:text-destructive"
                    @click="emit('clear')"
                >Очистить</button>
            </div>
        </PopoverContent>
    </Popover>
</template>
