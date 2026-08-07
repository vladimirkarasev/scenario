import type {DefineComponent} from 'vue'

export interface ComboboxItem {
  value: string
  label: string
}

export const Combobox: DefineComponent<{
  modelValue?: string
  items?: ComboboxItem[]
  placeholder?: string
  emptyText?: string
  disabled?: boolean
  size?: string
}, object, unknown, object, object, object, object, {
  'update:modelValue': (value: string) => void
}>
