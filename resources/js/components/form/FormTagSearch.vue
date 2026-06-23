<script setup lang="ts">
import { ref } from 'vue'
import { Search, X } from 'lucide-vue-next'
import FormField from './FormField.vue'
import { useFieldId } from './useFieldId'

type Item = Record<string, unknown>

const props = withDefaults(defineProps<{
    modelValue: Item[]
    loader: (q: string) => Promise<Item[]>
    valueKey: string
    displayKey: string
    id?: string
    name?: string
    label?: string
    hint?: string
    error?: string
    required?: boolean
    placeholder?: string
    debounceMs?: number
}>(), {
    required: false,
    debounceMs: 250,
    placeholder: 'Поиск…',
})

const emit = defineEmits<{
    'update:modelValue': [value: Item[]]
}>()

const fieldId = useFieldId(() => props.id, () => props.name)
const query = ref('')
const results = ref<Item[]>([])
const open = ref(false)
let timer: ReturnType<typeof setTimeout> | null = null

function onInput(): void {
    open.value = false
    if (timer) clearTimeout(timer)
    if (!query.value.trim()) { results.value = []; return }
    timer = setTimeout(async () => {
        try {
            const all = await props.loader(query.value)
            const taken = new Set(props.modelValue.map(m => String(m[props.valueKey])))
            results.value = all.filter(r => !taken.has(String(r[props.valueKey])))
            open.value = results.value.length > 0
        } catch { /* silent */ }
    }, props.debounceMs)
}

function add(item: Item): void {
    emit('update:modelValue', [...props.modelValue, item])
    query.value = ''
    results.value = []
    open.value = false
}

function remove(val: unknown): void {
    emit('update:modelValue', props.modelValue.filter(m => m[props.valueKey] !== val))
}

function closeSoon(): void {
    setTimeout(() => { open.value = false }, 150)
}
</script>

<template>
    <FormField :label="label" :hint="hint" :error="error" :required="required" :for="fieldId">
        <div class="space-y-2">
            <div v-if="modelValue.length" class="flex flex-wrap gap-1.5">
                <span
                    v-for="item in modelValue"
                    :key="String(item[valueKey])"
                    class="inline-flex items-center gap-1 rounded-lg bg-blue-50 px-2 py-1 text-[12px] font-medium text-blue-700"
                >
                    {{ item[displayKey] }}
                    <button
                        type="button"
                        class="text-blue-500 transition hover:text-blue-800"
                        :aria-label="`Удалить ${item[displayKey]}`"
                        @click="remove(item[valueKey])"
                    >
                        <X :size="11" />
                    </button>
                </span>
            </div>
            <div class="relative">
                <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400" :size="14" />
                <input
                    :id="fieldId"
                    :name="name || fieldId"
                    v-model="query"
                    type="text"
                    role="combobox"
                    :aria-expanded="open"
                    aria-autocomplete="list"
                    autocomplete="off"
                    class="h-8 w-full rounded-lg border border-slate-200 bg-white pl-8 pr-2.5 text-[12px] outline-none transition focus:border-blue-300 focus:ring-2 focus:ring-blue-100"
                    :placeholder="placeholder"
                    @input="onInput"
                    @blur="closeSoon"
                />
                <div
                    v-if="open && results.length"
                    class="absolute left-0 right-0 top-full z-20 mt-1 max-h-48 overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-lg"
                >
                    <button
                        v-for="item in results"
                        :key="String(item[valueKey])"
                        type="button"
                        class="flex w-full items-center px-3 py-2 text-left text-[12px] text-slate-800 transition hover:bg-blue-50"
                        @mousedown.prevent="add(item)"
                    >
                        {{ item[displayKey] }}
                    </button>
                </div>
            </div>
        </div>
    </FormField>
</template>
