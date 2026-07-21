<script setup lang="ts">
import {ref, computed, watch, onMounted, onUnmounted, nextTick} from 'vue'
import IMask from 'imask'
import PrimeDatePicker from 'primevue/datepicker'
import {CalendarIcon, ChevronLeftIcon, ChevronRightIcon, ChevronUpIcon, ChevronDownIcon, XIcon} from 'lucide-vue-next'

interface Props {
  modelValue: string
  showTime?: boolean
  placeholder?: string
  clearable?: boolean
  disabled?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  showTime: false,
  clearable: false,
  disabled: false,
  placeholder: undefined,
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

defineOptions({inheritAttrs: false})

const containerRef = ref<HTMLElement>()
const dropdownRef = ref<HTMLElement>()
const inputRef = ref<HTMLInputElement>()

const isOpen = ref(false)
const dropdownStyle = ref<Record<string, string>>({})
const DROPDOWN_HEIGHT_ESTIMATE = 320

async function openDropdown() {
  if (!containerRef.value) return
  const rect = containerRef.value.getBoundingClientRect()
  const spaceBelow = window.innerHeight - rect.bottom
  const spaceAbove = rect.top

  isOpen.value = true
  await nextTick()

  const actualHeight = dropdownRef.value?.offsetHeight ?? DROPDOWN_HEIGHT_ESTIMATE
  const openAbove = spaceBelow < actualHeight + 8 && spaceAbove >= actualHeight + 8

  dropdownStyle.value = openAbove
      ? {bottom: `${window.innerHeight - rect.top + 4}px`, left: `${rect.left}px`}
      : {top: `${rect.bottom + 4}px`, left: `${rect.left}px`}
}

function toggleDropdown() {
  if (isOpen.value) {
    isOpen.value = false;
    return
  }
  openDropdown()
}

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

function handleOutsideClick(e: MouseEvent) {
  if (
      containerRef.value?.contains(e.target as Node) ||
      dropdownRef.value?.contains(e.target as Node)
  ) return
  isOpen.value = false
  if (!im?.unmaskedValue) im?.updateOptions({lazy: true})
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

  document.addEventListener('mousedown', handleOutsideClick)
})

onUnmounted(() => {
  im?.destroy()
  im = null
  document.removeEventListener('mousedown', handleOutsideClick)
})

watch(() => props.modelValue, (val) => {
  if (!im) return
  const display = isoToDisplay(val)
  if (im.value !== display) im.value = display
})

function onFocus() {
  if (!props.disabled) {
    im?.updateOptions({lazy: false})
    openDropdown()
  }
}

function onBlur() {
  if (!im?.unmaskedValue) im?.updateOptions({lazy: true})
}

function clear() {
  if (im) {
    im.value = ''
    im.updateOptions({lazy: true})
  }
  emit('update:modelValue', '')
  isOpen.value = false
}

const calendarValue = computed({
  get(): Date | null {
    if (!props.modelValue) return null
    const d = new Date(props.modelValue)
    return isNaN(d.getTime()) ? null : d
  },
  set(val: Date | null) {
    if (!val) {
      emit('update:modelValue', '');
      return
    }
    const pad = (n: number) => String(n).padStart(2, '0')
    const date = `${val.getFullYear()}-${pad(val.getMonth() + 1)}-${pad(val.getDate())}`
    emit('update:modelValue', props.showTime
        ? `${date}T${pad(val.getHours())}:${pad(val.getMinutes())}`
        : date
    )
    if (!props.showTime) {
      isOpen.value = false
    }
  },
})

type DayContext = { selected: boolean; today: boolean; disabled: boolean; otherMonth: boolean }

const calendarPt = {
  panel: {class: ''},
  calendarContainer: {class: 'flex'},
  calendar: {class: ''},
  header: {class: 'flex items-center justify-between pb-2 mb-1'},
  pcPrevButton: {root: {class: 'inline-flex size-8 items-center justify-center rounded-md border-0 bg-transparent hover:bg-accent hover:text-accent-foreground transition-colors cursor-pointer'}},
  pcNextButton: {root: {class: 'inline-flex size-8 items-center justify-center rounded-md border-0 bg-transparent hover:bg-accent hover:text-accent-foreground transition-colors cursor-pointer'}},
  title: {class: 'flex items-center gap-1 text-sm font-medium select-none'},
  selectMonth: {class: 'cursor-pointer hover:text-primary transition-colors border-0 bg-transparent font-medium'},
  selectYear: {class: 'cursor-pointer hover:text-primary transition-colors border-0 bg-transparent font-medium'},
  dayView: {class: ''},
  tableHeader: {class: ''},
  tableHeaderRow: {class: ''},
  tableHeaderCell: {class: 'p-0 text-center'},
  weekDay: {class: 'flex size-8 items-center justify-center text-[0.75rem] text-muted-foreground font-normal'},
  tableBody: {class: ''},
  tableBodyRow: {class: ''},
  dayCell: {class: 'p-0'},
  day: ({context}: { context: DayContext }) => ({
    class: [
      'flex size-8 items-center justify-center rounded-md text-sm transition-colors cursor-pointer border-0 bg-transparent w-full',
      context.selected
          ? 'bg-primary text-primary hover:bg-primary/90'
          : context.today
              ? 'bg-accent text-red-600 hover:bg-accent/80'
              : 'hover:bg-accent hover:text-accent-foreground',
      context.otherMonth && !context.selected ? 'text-muted-foreground opacity-40' : '',
      context.disabled ? 'opacity-30 cursor-not-allowed pointer-events-none' : '',
    ].filter(Boolean).join(' '),
  }),
  timePicker: {class: 'mt-3 border-t border-border pt-3 flex items-center justify-center gap-2'},
  hourPicker: {class: 'flex flex-col items-center gap-1'},
  minutePicker: {class: 'flex flex-col items-center gap-1'},
  separatorContainer: {class: 'flex items-center self-center'},
  separator: {class: 'text-muted-foreground font-medium'},
  pcIncrementButton: {root: {class: 'inline-flex size-7 items-center justify-center rounded-md border-0 bg-transparent hover:bg-accent hover:text-accent-foreground transition-colors cursor-pointer'}},
  pcDecrementButton: {root: {class: 'inline-flex size-7 items-center justify-center rounded-md border-0 bg-transparent hover:bg-accent hover:text-accent-foreground transition-colors cursor-pointer'}},
  hour: {class: 'text-sm font-medium w-8 text-center'},
  minute: {class: 'text-sm font-medium w-8 text-center'},
}
</script>

<template>
  <div ref="containerRef" v-bind="$attrs" class="relative w-full">
    <input
        ref="inputRef"
        :placeholder="placeholder ?? (showTime ? 'дд.мм.гггг чч:мм' : 'дд.мм.гггг')"
        :disabled="disabled"
        class="flex h-9 w-full rounded-xl border border-input bg-background px-3 pr-9 py-1 text-sm transition-colors placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
        @focus="onFocus"
        @blur="onBlur"
    />

    <div class="absolute right-2.5 top-1/2 -translate-y-1/2 flex items-center gap-1">
      <button
          v-if="clearable && modelValue"
          type="button"
          tabindex="-1"
          class="flex items-center text-muted-foreground hover:text-foreground transition-colors disabled:cursor-not-allowed disabled:opacity-50"
          @mousedown.prevent
          @click="clear"
          :disabled="disabled"
      >
        <XIcon class="size-4"/>
      </button>
      <button
          type="button"
          tabindex="-1"
          :disabled="disabled"
          class="flex items-center text-muted-foreground hover:text-foreground transition-colors disabled:cursor-not-allowed disabled:opacity-50"
          @mousedown.prevent
          @click="toggleDropdown"
      >
        <CalendarIcon class="size-4"/>
      </button>
    </div>
  </div>

  <Teleport to="body">
    <div
        v-if="isOpen"
        ref="dropdownRef"
        class="fixed z-[100000] w-auto rounded-lg border border-border bg-popover p-3 shadow-md text-popover-foreground pointer-events-auto"
        :style="dropdownStyle"
        @mousedown.prevent
        @pointerdown.stop
    >
      <PrimeDatePicker
          v-model="calendarValue"
          :show-time="showTime"
          hour-format="24"
          inline
          unstyled
          :pt="calendarPt"
      >
        <template #previcon>
          <ChevronLeftIcon class="size-4"/>
        </template>
        <template #nexticon>
          <ChevronRightIcon class="size-4"/>
        </template>
        <template #incrementicon>
          <ChevronUpIcon class="size-4"/>
        </template>
        <template #decrementicon>
          <ChevronDownIcon class="size-4"/>
        </template>
      </PrimeDatePicker>
    </div>
  </Teleport>
</template>
