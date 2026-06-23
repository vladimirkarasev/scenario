<script setup lang="ts">
import { ref, computed, nextTick } from 'vue'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { Input } from '@/components/ui/input'
import { ChevronsUpDown, X, Check } from 'lucide-vue-next'
import type { SelectShape } from '@/lib/select-shape'
import { isSelectShape } from '@/lib/select-shape'

interface SelectOption {
    id: string | number
    value: string
    label: string
    parentId?: string | number | null
}

type IncomingModelValue =
    | SelectShape
    | SelectShape[]
    | string
    | string[]
    | null

const props = withDefaults(defineProps<{
    modelValue: IncomingModelValue
    options: SelectOption[]
    multiple?: boolean
    allowRootSelection?: boolean
    defaultSearch?: string
    disabled?: boolean
    error?: boolean
}>(), {
    multiple: false,
    allowRootSelection: true,
    defaultSearch: '',
    disabled: false,
    error: false,
})

const emit = defineEmits<{
    'update:modelValue': [value: SelectShape | SelectShape[] | null]
}>()

function toShape(value: string): SelectShape {
    const opt = props.options.find((o) => o.value === value)
    return { value, label: opt?.label ?? value }
}

function modelToValues(v: IncomingModelValue): string[] {
    if (v === null || v === undefined || v === '') return []
    const arr = Array.isArray(v) ? v : [v]
    return arr
        .map((it) => (isSelectShape(it) ? it.value : typeof it === 'string' ? it : ''))
        .filter((s) => s !== '')
}

function currentShapes(): SelectShape[] {
    const v = props.modelValue
    if (v === null) return []
    const arr = Array.isArray(v) ? v : [v]
    const out: SelectShape[] = []
    for (const it of arr) {
        if (isSelectShape(it)) out.push(it)
        else if (typeof it === 'string' && it !== '') out.push(toShape(it))
    }
    return out
}

function labelFromModel(value: string): string | null {
    const v = props.modelValue
    if (v === null) return null
    const arr = Array.isArray(v) ? v : [v]
    for (const it of arr) {
        if (isSelectShape(it) && it.value === value) return it.label
    }
    return null
}

const open = ref(false)
const search = ref('')
const searchRef = ref<InstanceType<typeof Input> | null>(null)

const selectedValues = computed<string[]>(() => modelToValues(props.modelValue))

const hasSelection = computed(() => selectedValues.value.length > 0)

const selectedItems = computed(() =>
    selectedValues.value.map((v) => ({
        value: v,
        label: labelFromModel(v) ?? props.options.find((o) => o.value === v)?.label ?? v,
    })),
)

function removeOne(value: string, e: MouseEvent): void {
    e.stopPropagation()
    if (props.disabled) return
    if (props.multiple) {
        emit('update:modelValue', currentShapes().filter((s) => s.value !== value))
    } else {
        emit('update:modelValue', null)
    }
}

const hasGroups = computed(() => props.options.some((o) => o.parentId))

const filteredOptions = computed(() => {
    const q = search.value.trim().toLowerCase()
    if (!q) return props.options
    return props.options.filter(
        (o) => o.label.toLowerCase().includes(q) || o.value.toLowerCase().includes(q),
    )
})

const filteredRootOptions = computed(() =>
    filteredOptions.value.filter((o) => !o.parentId),
)

function getChildOptions(parentId: string | number): SelectOption[] {
    return props.options.filter((o) => String(o.parentId) === String(parentId))
}

function hasChildren(optionId: string | number): boolean {
    return props.options.some((o) => String(o.parentId) === String(optionId))
}

function isRootSelectable(option: SelectOption): boolean {
    return !hasChildren(option.id) || props.allowRootSelection
}

function getDepth(option: SelectOption): number {
    let depth = 0
    let current: SelectOption | undefined = option
    const visited = new Set<string | number>()
    while (current?.parentId && !visited.has(current.id)) {
        visited.add(current.id)
        depth++
        current = props.options.find((o) => String(o.id) === String(current!.parentId))
    }
    return depth
}

function isSelected(value: string): boolean {
    return selectedValues.value.includes(value)
}

function toggle(value: string): void {
    if (props.disabled) return
    const shape = toShape(value)
    if (props.multiple) {
        const current = currentShapes()
        const idx = current.findIndex((s) => s.value === value)
        const next = idx > -1
            ? current.filter((s) => s.value !== value)
            : [...current, shape]
        emit('update:modelValue', next)
    } else {
        emit('update:modelValue', isSelected(value) ? null : shape)
        open.value = false
        search.value = ''
    }
}

function clearAll(e: MouseEvent): void {
    e.stopPropagation()
    emit('update:modelValue', props.multiple ? [] : null)
}

async function onOpenChange(val: boolean): Promise<void> {
    open.value = val
    if (val) {
        search.value = props.defaultSearch
        await nextTick()
        searchRef.value?.$el?.querySelector('input')?.focus()
    } else {
        search.value = ''
    }
}
</script>

<template>
    <Popover :open="open" @update:open="onOpenChange">
        <PopoverTrigger as-child>
            <button
                type="button"
                :disabled="disabled"
                class="flex min-h-9 w-full items-center gap-2 rounded-xl border px-3 py-1.5 text-left text-sm transition disabled:cursor-not-allowed disabled:opacity-50"
                :class="[
                    error
                        ? 'border-destructive focus:ring-1 focus:ring-destructive/30'
                        : 'border-input hover:border-slate-300 focus:border-ring focus:ring-1 focus:ring-ring',
                    open ? (error ? 'border-destructive ring-1 ring-destructive/30' : 'border-ring ring-1 ring-ring') : '',
                ]"
            >
                <!-- Chips -->
                <div class="flex min-w-0 flex-1 flex-wrap gap-1">
                    <template v-if="hasSelection">
                        <span
                            v-for="item in selectedItems"
                            :key="item.value"
                            class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700"
                        >
                            <span class="max-w-[160px] truncate">{{ item.label }}</span>
                            <X
                                v-if="!disabled"
                                class="size-3 shrink-0 text-slate-400 transition hover:text-slate-700"
                                @click="removeOne(item.value, $event)"
                            />
                        </span>
                    </template>
                    <span v-else class="text-muted-foreground">Выберите...</span>
                </div>

                <span class="flex shrink-0 items-center gap-1 self-center">
                    <X
                        v-if="hasSelection && !disabled"
                        class="size-3.5 text-muted-foreground transition hover:text-foreground"
                        @click="clearAll"
                    />
                    <ChevronsUpDown class="size-3.5 text-muted-foreground" />
                </span>
            </button>
        </PopoverTrigger>

        <PopoverContent
            align="start"
            :side-offset="4"
            class="w-[var(--reka-popover-trigger-width)] min-w-[200px] gap-0 p-0"
        >
            <!-- Search -->
            <div class="p-2 pb-1">
                <Input
                    ref="searchRef"
                    v-model="search"
                    placeholder="Поиск..."
                    class="h-8 text-sm"
                    @keydown.escape="onOpenChange(false)"
                    @keydown.enter.prevent="filteredOptions.length === 1 && toggle(filteredOptions[0].value)"
                />
            </div>

            <!-- Empty -->
            <div v-if="filteredOptions.length === 0" class="py-6 text-center text-sm text-muted-foreground">
                Ничего не найдено
            </div>

            <div v-else class="max-h-60 overflow-y-auto py-1">
                <!-- Flat search results -->
                <template v-if="search.trim()">
                    <button
                        v-for="option in filteredOptions"
                        :key="String(option.id)"
                        type="button"
                        class="flex w-full items-center gap-2.5 px-3 py-2 text-sm transition hover:bg-accent"
                        :class="isSelected(option.value) ? 'text-foreground font-medium' : 'text-foreground/80'"
                        @click="toggle(option.value)"
                    >
                        <!-- Checkbox -->
                        <span
                            v-if="multiple"
                            class="flex size-4 shrink-0 items-center justify-center rounded border"
                            :class="isSelected(option.value)
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-border'"
                        >
                            <Check v-if="isSelected(option.value)" class="size-3" />
                        </span>
                        <!-- Radio dot -->
                        <span
                            v-else
                            class="flex size-4 shrink-0 items-center justify-center rounded-full border"
                            :class="isSelected(option.value) ? 'border-primary' : 'border-border'"
                        >
                            <span v-if="isSelected(option.value)" class="size-2 rounded-full bg-primary" />
                        </span>

                        <span class="min-w-0 truncate text-left">
                            <span v-if="hasGroups && option.parentId" class="mr-1 text-muted-foreground">—</span>
                            {{ option.label }}
                        </span>
                    </button>
                </template>

                <!-- Tree -->
                <template v-else-if="hasGroups">
                    <template v-for="root in filteredRootOptions" :key="String(root.id)">
                        <button
                            type="button"
                            class="flex w-full items-center gap-2.5 px-3 py-2 text-sm transition"
                            :class="[
                                isRootSelectable(root)
                                    ? 'hover:bg-accent cursor-pointer'
                                    : 'cursor-default opacity-50',
                                isSelected(root.value) ? 'text-foreground font-medium' : 'text-foreground/80',
                            ]"
                            :disabled="!isRootSelectable(root)"
                            @click="isRootSelectable(root) && toggle(root.value)"
                        >
                            <span
                                v-if="multiple"
                                class="flex size-4 shrink-0 items-center justify-center rounded border"
                                :class="isSelected(root.value)
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-border'"
                            >
                                <Check v-if="isSelected(root.value)" class="size-3" />
                            </span>
                            <span
                                v-else
                                class="flex size-4 shrink-0 items-center justify-center rounded-full border"
                                :class="isSelected(root.value) ? 'border-primary' : 'border-border'"
                            >
                                <span v-if="isSelected(root.value)" class="size-2 rounded-full bg-primary" />
                            </span>
                            <span class="min-w-0 truncate text-left font-medium">{{ root.label }}</span>
                        </button>

                        <button
                            v-for="child in getChildOptions(root.id)"
                            :key="String(child.id)"
                            type="button"
                            class="flex w-full items-center gap-2.5 py-2 pr-3 text-sm transition hover:bg-accent"
                            :class="isSelected(child.value) ? 'text-foreground font-medium' : 'text-foreground/80'"
                            :style="{ paddingLeft: (getDepth(child) * 16 + 12) + 'px' }"
                            @click="toggle(child.value)"
                        >
                            <span
                                v-if="multiple"
                                class="flex size-4 shrink-0 items-center justify-center rounded border"
                                :class="isSelected(child.value)
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-border'"
                            >
                                <Check v-if="isSelected(child.value)" class="size-3" />
                            </span>
                            <span
                                v-else
                                class="flex size-4 shrink-0 items-center justify-center rounded-full border"
                                :class="isSelected(child.value) ? 'border-primary' : 'border-border'"
                            >
                                <span v-if="isSelected(child.value)" class="size-2 rounded-full bg-primary" />
                            </span>
                            <span class="min-w-0 truncate text-left">{{ child.label }}</span>
                        </button>
                    </template>
                </template>

                <!-- Flat list -->
                <template v-else>
                    <button
                        v-for="option in filteredOptions"
                        :key="String(option.id)"
                        type="button"
                        class="flex w-full items-center gap-2.5 px-3 py-2 text-sm transition hover:bg-accent"
                        :class="isSelected(option.value) ? 'text-foreground font-medium' : 'text-foreground/80'"
                        @click="toggle(option.value)"
                    >
                        <span
                            v-if="multiple"
                            class="flex size-4 shrink-0 items-center justify-center rounded border"
                            :class="isSelected(option.value)
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-border'"
                        >
                            <Check v-if="isSelected(option.value)" class="size-3" />
                        </span>
                        <span
                            v-else
                            class="flex size-4 shrink-0 items-center justify-center rounded-full border"
                            :class="isSelected(option.value) ? 'border-primary' : 'border-border'"
                        >
                            <span v-if="isSelected(option.value)" class="size-2 rounded-full bg-primary" />
                        </span>
                        <span class="min-w-0 truncate text-left">{{ option.label }}</span>
                    </button>
                </template>
            </div>

            <!-- Multi: selected count footer -->
            <div
                v-if="multiple && selectedValues.length > 0"
                class="flex items-center justify-between border-t border-border/60 px-3 py-2"
            >
                <span class="text-xs text-muted-foreground">Выбрано: {{ selectedValues.length }}</span>
                <button
                    type="button"
                    class="text-xs text-muted-foreground transition hover:text-destructive"
                    @click="emit('update:modelValue', [])"
                >
                    Очистить
                </button>
            </div>
        </PopoverContent>
    </Popover>
</template>
