<script setup lang="ts">
import {ref, computed, watch, onMounted, onUnmounted, nextTick} from 'vue'
import IMask from 'imask'
import {ChevronDown} from 'lucide-vue-next'
import type {GrzShape} from '@/lib/grz-shape'

export type GrzValue = GrzShape

interface GrzCountry {
  code: string
  name: string
  flag: string
  mask: string | RegExp
  definitions?: Record<string, RegExp>
  placeholder: string
}

const props = withDefaults(defineProps<{
  modelValue: GrzValue | string | null
  disabled?: boolean
  error?: boolean
}>(), {
  disabled: false,
  error: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: GrzValue | null]
}>()

function toFormattedString(v: GrzValue | string | null | undefined): string {
  if (!v) return ''
  if (typeof v === 'string') return v
  return v.formatted ?? ''
}

const RU_LETTERS = /[АВЕКМНОРСТУХ]/

const COUNTRIES: GrzCountry[] = [
  {code: 'RU', name: 'Россия', flag: '🇷🇺', mask: 'L000LL00[0]', definitions: {L: RU_LETTERS}, placeholder: 'А000АА00'},
  {code: 'BY', name: 'Беларусь', flag: '🇧🇾', mask: /^[A-Z0-9]{0,8}$/, placeholder: '0000AB0'},
  {code: 'KZ', name: 'Казахстан', flag: '🇰🇿', mask: /^[A-Z0-9]{0,9}$/, placeholder: '000ABC00'},
  {code: 'UA', name: 'Украина', flag: '🇺🇦', mask: /^[A-Z0-9]{0,8}$/, placeholder: 'AA0000AA'},
  {code: 'UZ', name: 'Узбекистан', flag: '🇺🇿', mask: /^[A-Z0-9]{0,9}$/, placeholder: '00A000AA'},
  {code: 'AM', name: 'Армения', flag: '🇦🇲', mask: /^[A-Z0-9]{0,8}$/, placeholder: '00AA000'},
  {code: 'GE', name: 'Грузия', flag: '🇬🇪', mask: /^[A-Z0-9]{0,8}$/, placeholder: 'AA000AA'},
  {code: 'AZ', name: 'Азербайджан', flag: '🇦🇿', mask: /^[A-Z0-9]{0,9}$/, placeholder: '00AA000'},
  {code: 'KG', name: 'Кыргызстан', flag: '🇰🇬', mask: /^[A-Z0-9]{0,9}$/, placeholder: '0000AAA'},
  {code: 'MD', name: 'Молдова', flag: '🇲🇩', mask: /^[A-Z0-9]{0,8}$/, placeholder: 'AA0000AA'},
]

defineOptions({inheritAttrs: false})

const inputRef = ref<HTMLInputElement>()
const searchRef = ref<HTMLInputElement>()
const containerRef = ref<HTMLElement>()
const dropdownRef = ref<HTMLElement>()
const isOpen = ref(false)
const dropdownStyle = ref({top: '0px', left: '0px'})
const selectedCountry = ref<GrzCountry>(COUNTRIES[0])
const countrySearch = ref('')

const filteredCountries = computed(() => {
  const q = countrySearch.value.trim().toLowerCase()
  if (!q) return COUNTRIES
  return COUNTRIES.filter((c) => c.name.toLowerCase().includes(q))
})

type ImaskInstance = ReturnType<typeof IMask>
let im: ImaskInstance | null = null
let externalUpdate = false

function applyMask(initialValue = '') {
  if (!inputRef.value) return
  im?.destroy()
  const country = selectedCountry.value
  im = country.definitions
      ? IMask(inputRef.value, {
        mask: country.mask as string,
        definitions: country.definitions,
        prepare: (str: string) => str.toUpperCase(),
      })
      : IMask(inputRef.value, {
        mask: country.mask as RegExp,
        prepare: (str: string) => str.toUpperCase(),
      })
  im.on('accept', handleAccept)
  if (initialValue) im.value = initialValue
}

function handleAccept() {
  if (!im || externalUpdate) return
  const formatted = im.value
  if (!formatted) {
    emit('update:modelValue', null)
    return
  }
  emit('update:modelValue', {
    country: selectedCountry.value.code,
    formatted,
    original: im.unmaskedValue,
  })
}

onMounted(() => {
  const initial = toFormattedString(props.modelValue)
  const v = props.modelValue
  if (v && typeof v === 'object') {
    const fromCountry = COUNTRIES.find((c) => c.code === v.country)
    if (fromCountry) selectedCountry.value = fromCountry
  }
  applyMask(initial)
  document.addEventListener('mousedown', handleOutsideClick)
})

onUnmounted(() => {
  im?.destroy()
  im = null
  document.removeEventListener('mousedown', handleOutsideClick)
})

watch(() => props.modelValue, (val) => {
  if (!im || externalUpdate) return
  const display = toFormattedString(val)
  if (im.value !== display) {
    im.value = display
  }
})

function openDropdown() {
  if (!containerRef.value) return
  const rect = containerRef.value.getBoundingClientRect()
  dropdownStyle.value = {
    top: `${rect.bottom + 4}px`,
    left: `${rect.left}px`,
  }
  countrySearch.value = ''
  isOpen.value = true
  nextTick(() => searchRef.value?.focus())
}

function selectCountry(country: GrzCountry) {
  selectedCountry.value = country
  isOpen.value = false
  externalUpdate = true
  applyMask('')
  emit('update:modelValue', null)
  externalUpdate = false
}

function handleOutsideClick(e: MouseEvent) {
  if (
      containerRef.value?.contains(e.target as Node) ||
      dropdownRef.value?.contains(e.target as Node)
  ) return
  isOpen.value = false
}
</script>

<template>
  <div
      ref="containerRef"
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
        :disabled="disabled"
        tabindex="-1"
        :class="[
                error
            ? 'flex shrink-0 items-center gap-1 border-r border-destructive px-2.5 hover:bg-muted/50 transition-colors disabled:pointer-events-none'
            : 'flex shrink-0 items-center gap-1 border-r border-input px-2.5 hover:bg-muted/50 transition-colors disabled:pointer-events-none'
            ]"
        @mousedown.prevent
        @click="openDropdown"
    >
      <span class="text-base leading-none">{{ selectedCountry.flag }}</span>
      <ChevronDown class="size-3 shrink-0 text-muted-foreground" />
    </button>

    <input
        ref="inputRef"
        :disabled="disabled"
        :placeholder="selectedCountry.placeholder"
        class="flex-1 min-w-0 bg-transparent px-2.5 py-1 text-base uppercase md:text-sm outline-none placeholder:text-muted-foreground disabled:cursor-not-allowed border-0 focus:border-0"
        @focus="isOpen = false"
    >
  </div>

  <Teleport to="body">
    <div
        v-if="isOpen"
        ref="dropdownRef"
        class="fixed z-[9999] w-64 rounded-lg border border-border bg-popover shadow-md"
        :style="dropdownStyle"
    >
      <div class="p-2 border-b border-border">
        <input
            ref="searchRef"
            v-model="countrySearch"
            type="text"
            placeholder="Поиск..."
            class="w-full rounded-md border border-input bg-background px-2.5 py-1.5 text-sm outline-none placeholder:text-muted-foreground focus:ring-1 focus:ring-ring"
        >
      </div>
      <div class="max-h-52 overflow-y-auto py-1">
        <p v-if="!filteredCountries.length" class="px-3 py-2 text-sm text-muted-foreground">Ничего не найдено</p>
        <button
            v-for="country in filteredCountries"
            :key="country.code"
            type="button"
            class="flex w-full items-center gap-2.5 px-3 py-1.5 text-sm transition-colors hover:bg-accent hover:text-accent-foreground"
            :class="country.code === selectedCountry.code ? 'bg-accent/40 font-medium' : ''"
            @mousedown.prevent="selectCountry(country)"
        >
          <span class="text-base leading-none">{{ country.flag }}</span>
          <span class="flex-1 truncate text-left">{{ country.name }}</span>
        </button>
      </div>
    </div>
  </Teleport>
</template>
