<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted, computed } from 'vue'
import IMask from 'imask'
import { Minus, Plus } from 'lucide-vue-next'

const props = withDefaults(defineProps<{
    modelValue: number | string | null
    min?: number | null
    max?: number | null
    step?: number | null
    decimalPlaces?: number
    placeholder?: string
    disabled?: boolean
    error?: boolean
}>(), {
    min: null,
    max: null,
    step: null,
    decimalPlaces: 0,
    placeholder: '',
    disabled: false,
    error: false,
})

const emit = defineEmits<{
    'update:modelValue': [value: number | null]
}>()

defineOptions({ inheritAttrs: false })

const inputRef = ref<HTMLInputElement>()

type ImaskInstance = ReturnType<typeof IMask>
let im: ImaskInstance | null = null
let externalUpdate = false

const effectiveStep = computed(() => {
    if (props.step !== null && props.step !== undefined && props.step > 0) return props.step
    const dp = props.decimalPlaces ?? 0
    return dp > 0 ? Math.pow(10, -dp) : 1
})

const autoPlaceholder = computed(() => {
    if (props.placeholder) return props.placeholder
    const dp = props.decimalPlaces ?? 0
    return dp > 0 ? '0.' + '0'.repeat(dp) : '0'
})

function applyMask() {
    if (!inputRef.value) return
    im?.destroy()
    im = IMask(inputRef.value, {
        mask: Number,
        scale: props.decimalPlaces ?? 0,
        signed: true,
        thousandsSeparator: '',
        padFractionalZeros: (props.decimalPlaces ?? 0) > 0,
        normalizeZeros: true,
        radix: '.',
        mapToRadix: [','],
        min: props.min ?? undefined,
        max: props.max ?? undefined,
    })
    im.on('accept', handleAccept)
    const val = props.modelValue
    if (val !== null && val !== undefined && val !== '') {
        im.unmaskedValue = String(val)
    }
}

function handleAccept() {
    if (!im || externalUpdate) return
    const raw = im.unmaskedValue
    emit('update:modelValue', raw === '' || raw === '-' ? null : Number(raw))
}

function clamp(n: number): number {
    let v = n
    if (props.max !== null && props.max !== undefined && v > props.max) v = props.max
    if (props.min !== null && props.min !== undefined && v < props.min) v = props.min
    return v
}

function roundToDecimals(n: number): number {
    const factor = Math.pow(10, props.decimalPlaces ?? 0)
    return Math.round(n * factor) / factor
}

function increment() {
    if (props.disabled) return
    const base = props.min ?? 0
    const current = (props.modelValue !== null && props.modelValue !== undefined && props.modelValue !== '') ? Number(props.modelValue) : base
    emit('update:modelValue', roundToDecimals(clamp(current + effectiveStep.value)))
}

function decrement() {
    if (props.disabled) return
    const base = props.min ?? 0
    const current = (props.modelValue !== null && props.modelValue !== undefined && props.modelValue !== '') ? Number(props.modelValue) : base
    emit('update:modelValue', roundToDecimals(clamp(current - effectiveStep.value)))
}

watch(() => props.modelValue, (val) => {
    if (!im || externalUpdate) return
    externalUpdate = true
    im.unmaskedValue = (val !== null && val !== undefined && val !== '') ? String(val) : ''
    externalUpdate = false
})

watch([() => props.decimalPlaces, () => props.min, () => props.max], () => {
    const saved = im?.unmaskedValue ?? ''
    applyMask()
    if (saved && im) im.unmaskedValue = saved
})

onMounted(() => applyMask())
onUnmounted(() => { im?.destroy(); im = null })
</script>

<template>
    <div
        v-bind="$attrs"
        class="relative flex h-9 w-full overflow-hidden rounded-xl border bg-transparent transition-colors"
        :class="[
            error
                ? 'border-destructive focus-within:border-destructive focus-within:ring-3 focus-within:ring-destructive/30'
                : 'border-input focus-within:border-ring focus-within:ring-3 focus-within:ring-ring/50',
            disabled ? 'opacity-50 pointer-events-none cursor-not-allowed' : '',
        ]"
    >
        <button
            type="button"
            tabindex="-1"
            class="flex shrink-0 items-center justify-center w-7 border-r transition-colors hover:bg-muted/50  disabled:cursor-not-allowed disabled:opacity-50"
            :class="error ? 'border-destructive' : 'border-input'"
            :disabled="disabled"
            @mousedown.prevent
            @click="decrement"
        >
            <Minus class="size-3 text-muted-foreground" />
        </button>

        <input
            ref="inputRef"
            :placeholder="autoPlaceholder"
            :disabled="disabled"
            class="disabled:cursor-not-allowed disabled:opacity-50 flex-1 min-w-0 bg-transparent px-2 py-1 text-sm text-center outline-none placeholder:text-muted-foreground disabled:cursor-not-allowed border-0 focus:border-0"
        />

        <button
            type="button"
            tabindex="-1"
            class="flex shrink-0 items-center justify-center w-7 border-l transition-colors hover:bg-muted/50 disabled:cursor-not-allowed disabled:opacity-50"
            :class="error ? 'border-destructive' : 'border-input'"
            :disabled="disabled"
            @mousedown.prevent
            @click="increment"
        >
            <Plus class="size-3 text-muted-foreground" />
        </button>
    </div>
</template>
