<script setup lang="ts">
import {ref, computed, watch, onMounted, onUnmounted, nextTick} from 'vue'
import IMask from 'imask'
import {ChevronDown} from 'lucide-vue-next'
import type {PhoneShape} from '@/lib/phone-shape'

export type PhoneValue = PhoneShape

interface Country {
  code: string
  name: string
  flag: string
  dialCode: string
  mask: string
  trunk?: string
}

const props = withDefaults(defineProps<{
  modelValue: PhoneValue | string | null
  disabled?: boolean
  error?: boolean
}>(), {
  disabled: false,
  error: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: PhoneValue | null]
}>()

function toFormattedString(v: PhoneValue | string | null | undefined): string {
  if (!v) return ''
  if (typeof v === 'string') return v
  return v.formatted ?? ''
}

const COUNTRIES: Country[] = [
  {code: 'RU', name: 'Россия', flag: '🇷🇺', dialCode: '+7', mask: '+{7} (000) 000-00-00', trunk: '8'},
  {code: 'BY', name: 'Беларусь', flag: '🇧🇾', dialCode: '+375', mask: '+{375} (00) 000-00-00', trunk: '8'},
  {code: 'KZ', name: 'Казахстан', flag: '🇰🇿', dialCode: '+7', mask: '+{7} (000) 000-00-00', trunk: '8'},
  {code: 'UA', name: 'Украина', flag: '🇺🇦', dialCode: '+380', mask: '+{380} (00) 000-00-00', trunk: '0'},
  {code: 'UZ', name: 'Узбекистан', flag: '🇺🇿', dialCode: '+998', mask: '+{998} (00) 000-00-00', trunk: '8'},
  {code: 'AZ', name: 'Азербайджан', flag: '🇦🇿', dialCode: '+994', mask: '+{994} (00) 000-00-00', trunk: '0'},
  {code: 'AM', name: 'Армения', flag: '🇦🇲', dialCode: '+374', mask: '+{374} (00) 000-000', trunk: '0'},
  {code: 'GE', name: 'Грузия', flag: '🇬🇪', dialCode: '+995', mask: '+{995} (000) 000-000', trunk: '0'},
  {code: 'TJ', name: 'Таджикистан', flag: '🇹🇯', dialCode: '+992', mask: '+{992} (00) 000-0000', trunk: '8'},
  {code: 'TM', name: 'Туркменистан', flag: '🇹🇲', dialCode: '+993', mask: '+{993} (00) 000-000', trunk: '8'},
  {code: 'KG', name: 'Кыргызстан', flag: '🇰🇬', dialCode: '+996', mask: '+{996} (000) 000-000', trunk: '0'},
  {code: 'MD', name: 'Молдова', flag: '🇲🇩', dialCode: '+373', mask: '+{373} (0000) 0000', trunk: '0'},
  {code: 'US', name: 'США', flag: '🇺🇸', dialCode: '+1', mask: '+{1} (000) 000-0000', trunk: '1'},
  {code: 'GB', name: 'Великобритания', flag: '🇬🇧', dialCode: '+44', mask: '+{44} 00 0000 0000', trunk: '0'},
  {code: 'DE', name: 'Германия', flag: '🇩🇪', dialCode: '+49', mask: '+{49} 000 0000000', trunk: '0'},
  {code: 'FR', name: 'Франция', flag: '🇫🇷', dialCode: '+33', mask: '+{33} 0 00 00 00 00', trunk: '0'},
  {code: 'IT', name: 'Италия', flag: '🇮🇹', dialCode: '+39', mask: '+{39} 000 000 0000'},
  {code: 'ES', name: 'Испания', flag: '🇪🇸', dialCode: '+34', mask: '+{34} 000 000 000'},
  {code: 'PL', name: 'Польша', flag: '🇵🇱', dialCode: '+48', mask: '+{48} 000 000 000'},
  {code: 'TR', name: 'Турция', flag: '🇹🇷', dialCode: '+90', mask: '+{90} 000 000 00 00', trunk: '0'},
  {code: 'CN', name: 'Китай', flag: '🇨🇳', dialCode: '+86', mask: '+{86} 000 0000 0000', trunk: '0'},
]

defineOptions({inheritAttrs: false})

const inputRef = ref<HTMLInputElement>()
const searchRef = ref<HTMLInputElement>()
const containerRef = ref<HTMLElement>()
const dropdownRef = ref<HTMLElement>()
const isOpen = ref(false)
const dropdownStyle = ref({top: '0px', left: '0px'})
const selectedCountry = ref<Country>(COUNTRIES[0])
const countrySearch = ref('')

const phonePlaceholder = computed(() =>
    selectedCountry.value.mask
        .replace(/\{([^}]+)\}/g, '$1')
        .replace(/0/g, '9')
)

const filteredCountries = computed(() => {
  const q = countrySearch.value.trim().toLowerCase()
  if (!q) return COUNTRIES
  return COUNTRIES.filter(c =>
      c.name.toLowerCase().includes(q) || c.dialCode.includes(q)
  )
})

function findCountryByDigits(digits: string): Country | null {
  const sorted = [...COUNTRIES].sort((a, b) => b.dialCode.length - a.dialCode.length)
  return sorted.find(c => digits.startsWith(c.dialCode.replace(/\D/g, ''))) ?? null
}

type ImaskInstance = ReturnType<typeof IMask>
let im: ImaskInstance | null = null
const detecting = false
let externalUpdate = false

function applyMask(initialValue = '') {
  if (!inputRef.value) return
  im?.destroy()
  im = IMask(inputRef.value, {
    mask: selectedCountry.value.mask,
    lazy: true,
    placeholderChar: '_',
  })
  im.on('accept', handleAccept)
  if (initialValue) im.value = initialValue
}

function nationalLength(): number {
  return (selectedCountry.value.mask.match(/0/g) ?? []).length
}

function normalizeNationalDigits(raw: string): string {
  let digits = raw.replace(/\D/g, '')
  const codeDigits = selectedCountry.value.dialCode.replace(/\D/g, '')
  const trunk = selectedCountry.value.trunk ?? ''
  const nat = nationalLength()

  if (digits.length === nat + codeDigits.length && digits.startsWith(codeDigits)) {
    digits = digits.slice(codeDigits.length)
  } else if (trunk && digits.length === nat + trunk.length && digits.startsWith(trunk)) {
    digits = digits.slice(trunk.length)
  }

  return digits.slice(0, nat)
}

function handleAccept() {
  if (!im || detecting) return
  const formatted = im.value
  const digits = formatted.replace(/\D/g, '')
  if (!digits) {
    emit('update:modelValue', null)
    return
  }
  const codeDigits = selectedCountry.value.dialCode.replace(/\D/g, '')
  const national = digits.startsWith(codeDigits) ? digits.slice(codeDigits.length) : digits
  emit('update:modelValue', {
    country: selectedCountry.value.code,
    formatted,
    original: digits,
    national,
  })
}

function handlePaste(e: ClipboardEvent) {
  if (!im) return
  const text = e.clipboardData?.getData('text') ?? ''
  if (!text.trim()) return
  e.preventDefault()
  im.unmaskedValue = normalizeNationalDigits(text)
  handleAccept()
}

onMounted(() => {
  const initial = toFormattedString(props.modelValue)
  const v = props.modelValue
  if (initial) {
    const digits = initial.replace(/\D/g, '')
    const detected = findCountryByDigits(digits)
    if (detected) selectedCountry.value = detected
  } else if (v && typeof v === 'object') {
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

function selectCountry(country: Country) {
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
      <ChevronDown class="size-3 shrink-0 text-muted-foreground"/>
    </button>

    <input
        ref="inputRef"
        :disabled="disabled"
        :placeholder="phonePlaceholder"
        class="flex-1 min-w-0 bg-transparent px-2.5 py-1 text-base md:text-sm outline-none placeholder:text-muted-foreground disabled:cursor-not-allowed border-0 focus:border-0"
        @focus="isOpen = false"
        @paste="handlePaste"
    />
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
        />
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
          <span class="text-xs text-muted-foreground">{{ country.dialCode }}</span>
        </button>
      </div>
    </div>
  </Teleport>
</template>
