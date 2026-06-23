<script setup lang="ts">
import {Search} from 'lucide-vue-next'

defineProps<{
  modelValue: string
  placeholder?: string
}>()

defineEmits<{ 'update:modelValue': [value: string] }>()
</script>

<template>
  <div class="relative">
    <Search :size="14" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"/>
    <input
        :value="modelValue"
        type="text"
        :placeholder="placeholder ?? 'Поиск...'"
        class="h-9 w-full rounded-xl border border-slate-200 bg-white pl-9 pr-3 text-[13px] text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
        @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
    />
  </div>
</template>
