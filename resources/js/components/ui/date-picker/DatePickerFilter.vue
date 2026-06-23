<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import IMask from 'imask'
import DatePicker from 'primevue/datepicker'
import { CalendarIcon, ChevronLeftIcon, ChevronRightIcon, ChevronUpIcon, ChevronDownIcon } from 'lucide-vue-next'

interface Props {
    modelValue?: string
    showTime?: boolean
    placeholder?: string
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: '',
    showTime: false,
    placeholder: undefined,
})

const emit = defineEmits<{
    'update:modelValue': [value: string]
}>()

// ── DOM refs ─────────────────────────────────────────────────────────────────
const containerRef = ref<HTMLElement>()  // kept for potential future use
const dropdownRef = ref<HTMLElement>()
const inputRef = ref<HTMLInputElement>()

// ── Overlay state ─────────────────────────────────────────────────────────────
const isOpen = ref(false)
const dropdownStyle = ref({ top: '0px', left: '0px' })

function openDropdown() {
    if (!containerRef.value) return
    const rect = containerRef.value.getBoundingClientRect()
    dropdownStyle.value = {
        top: `${rect.bottom + 4}px`,
        left: `${rect.left}px`,
    }
    isOpen.value = true
}

function toggleDropdown() {
    if (isOpen.value) { isOpen.value = false; return }
    openDropdown()
}

// ── IMask ─────────────────────────────────────────────────────────────────────
type ImaskInstance = ReturnType<typeof IMask>
let im: ImaskInstance | null = null

const maskPattern = computed(() => props.showTime ? '00.00.0000 00:00' : '00.00.0000')

function isoToDisplay(iso: string): string {
    if (!iso) return ''
    const d = new Date(iso)
    if (isNaN(d.getTime())) return ''
    const pad = (n: number) => String(n).padStart(2, '0')
    const datePart = `${pad(d.getDate())}.${pad(d.getMonth() + 1)}.${d.getFullYear()}`
    return props.showTime
        ? `${datePart} ${pad(d.getHours())}:${pad(d.getMinutes())}`
        : datePart
}

function emitFromMask() {
    if (!im) return
    if (!im.masked.isComplete) {
        if (!im.unmaskedValue) emit('update:modelValue', '')
        return
    }
    const [datePart, timePart] = im.value.split(' ')
    const [dd, mm, yyyy] = datePart.split('.')
    if (props.showTime && timePart) {
        const [hh, mi] = timePart.split(':')
        const iso = `${yyyy}-${mm}-${dd}T${hh}:${mi}`
        if (iso !== props.modelValue) emit('update:modelValue', iso)
    } else if (!props.showTime) {
        const iso = `${yyyy}-${mm}-${dd}`
        if (iso !== props.modelValue) emit('update:modelValue', iso)
    }
}

onMounted(() => {
    if (!inputRef.value) return
    im = IMask(inputRef.value, {
        mask: maskPattern.value,
        lazy: true,
        placeholderChar: '_',
    })
    im.on('accept', emitFromMask)

    if (props.modelValue) {
        im.value = isoToDisplay(props.modelValue)
    }
})

onUnmounted(() => {
    im?.destroy()
    im = null
})

watch(() => props.modelValue, (val) => {
    if (!im) return
    const display = isoToDisplay(val)
    if (im.value !== display) im.value = display
})

function onFocus() {
    im?.updateOptions({ lazy: false })
    openDropdown()
}

function onBlur() {
    isOpen.value = false
    if (!im?.unmaskedValue) im?.updateOptions({ lazy: true })
}

// ── Calendar v-model ──────────────────────────────────────────────────────────
const calendarValue = computed({
    get(): Date | null {
        if (!props.modelValue) return null
        const d = new Date(props.modelValue)
        return isNaN(d.getTime()) ? null : d
    },
    set(val: Date | null) {
        if (!val) { emit('update:modelValue', ''); return }
        const pad = (n: number) => String(n).padStart(2, '0')
        const date = `${val.getFullYear()}-${pad(val.getMonth() + 1)}-${pad(val.getDate())}`
        emit('update:modelValue', props.showTime
            ? `${date}T${pad(val.getHours())}:${pad(val.getMinutes())}`
            : date
        )
        if (!props.showTime) isOpen.value = false
    },
})

// ── Calendar PT ───────────────────────────────────────────────────────────────
type DayContext = { selected: boolean; today: boolean; disabled: boolean; otherMonth: boolean }

const calendarPt = {
    panel: { class: '' },
    calendarContainer: { class: 'flex' },
    calendar: { class: '' },
    header: { class: 'flex items-center justify-between pb-2 mb-1' },
    pcPrevButton: { root: { class: 'inline-flex size-7 items-center justify-center rounded-md border-0 bg-transparent hover:bg-accent hover:text-accent-foreground transition-colors cursor-pointer' } },
    pcNextButton: { root: { class: 'inline-flex size-7 items-center justify-center rounded-md border-0 bg-transparent hover:bg-accent hover:text-accent-foreground transition-colors cursor-pointer' } },
    title: { class: 'flex items-center gap-1 text-sm font-medium select-none' },
    selectMonth: { class: 'cursor-pointer hover:text-primary transition-colors border-0 bg-transparent font-medium' },
    selectYear: { class: 'cursor-pointer hover:text-primary transition-colors border-0 bg-transparent font-medium' },
    dayView: { class: '' },
    tableHeader: { class: '' },
    tableHeaderRow: { class: '' },
    tableHeaderCell: { class: 'p-0 text-center' },
    weekDay: { class: 'flex size-8 items-center justify-center text-[0.7rem] text-muted-foreground font-normal' },
    tableBody: { class: '' },
    tableBodyRow: { class: '' },
    dayCell: { class: 'p-0' },
    day: ({ context }: { context: DayContext }) => ({
        class: [
            'flex size-8 items-center justify-center rounded-md text-xs transition-colors cursor-pointer border-0 bg-transparent w-full',
            context.selected
                ? 'bg-primary text-primary-foreground hover:bg-primary/90'
                : context.today
                    ? 'bg-accent text-accent-foreground font-semibold hover:bg-accent/80'
                    : 'hover:bg-accent hover:text-accent-foreground',
            context.otherMonth && !context.selected ? 'text-muted-foreground opacity-40' : '',
            context.disabled ? 'opacity-30 cursor-not-allowed pointer-events-none' : '',
        ].filter(Boolean).join(' '),
    }),
    timePicker: { class: 'mt-3 border-t border-border pt-3 flex items-center justify-center gap-2' },
    hourPicker: { class: 'flex flex-col items-center gap-1' },
    minutePicker: { class: 'flex flex-col items-center gap-1' },
    separatorContainer: { class: 'flex items-center self-center' },
    separator: { class: 'text-muted-foreground font-medium' },
    pcIncrementButton: { root: { class: 'inline-flex size-7 items-center justify-center rounded-md border-0 bg-transparent hover:bg-accent hover:text-accent-foreground transition-colors cursor-pointer' } },
    pcDecrementButton: { root: { class: 'inline-flex size-7 items-center justify-center rounded-md border-0 bg-transparent hover:bg-accent hover:text-accent-foreground transition-colors cursor-pointer' } },
    hour: { class: 'text-sm font-medium w-8 text-center' },
    minute: { class: 'text-sm font-medium w-8 text-center' },
}
</script>

<template>
    <div ref="containerRef" class="relative w-full">
        <input
            ref="inputRef"
            :placeholder="placeholder ?? (showTime ? 'дд.мм.гггг чч:мм' : 'дд.мм.гггг')"
            class="flex h-7 w-full rounded-md border border-input bg-background px-2 pr-7 py-0 text-xs shadow-sm transition-colors placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring"
            @focus="onFocus"
            @blur="onBlur"
        />
        <button
            type="button"
            tabindex="-1"
            class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center text-muted-foreground hover:text-foreground transition-colors"
            @mousedown.prevent
            @click="toggleDropdown"
        >
            <CalendarIcon class="size-3.5" />
        </button>
    </div>

    <Teleport to="body">
        <div
            v-if="isOpen"
            ref="dropdownRef"
            class="fixed z-[9999] w-auto rounded-lg border border-border bg-popover p-3 shadow-md text-popover-foreground"
            :style="dropdownStyle"
            @mousedown.prevent
        >
            <DatePicker
                v-model="calendarValue"
                :show-time="showTime"
                hour-format="24"
                inline
                unstyled
                :pt="calendarPt"
            >
                <template #previcon><ChevronLeftIcon class="size-4" /></template>
                <template #nexticon><ChevronRightIcon class="size-4" /></template>
                <template #incrementicon><ChevronUpIcon class="size-3.5" /></template>
                <template #decrementicon><ChevronDownIcon class="size-3.5" /></template>
            </DatePicker>
        </div>
    </Teleport>
</template>
