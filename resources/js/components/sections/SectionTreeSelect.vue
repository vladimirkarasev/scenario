<script setup lang="ts">
import {computed, ref, watch} from 'vue'
import {Check, Loader2} from 'lucide-vue-next'
import type {SectionCategory} from '@/types/section'

const props = withDefaults(defineProps<{
  modelValue: string[]
  // Готовый реактивный список разделов (если уже загружен снаружи).
  items?: SectionCategory[]
  // Ленивая загрузка полного плоского списка разделов модуля (для модалок).
  loadAll?: () => Promise<SectionCategory[]>
  // Перезагружать список при открытии (чтобы видеть только что созданные разделы).
  open?: boolean
}>(), {open: true})

const emit = defineEmits<{ 'update:modelValue': [value: string[]] }>()

const internal = ref<SectionCategory[]>([])
const loading = ref(false)
const loaded = ref(false)

// items имеет приоритет; иначе используем то, что подгрузили через loadAll.
const categories = computed<SectionCategory[]>(() => props.items ?? internal.value)

async function load(): Promise<void> {
  if (props.items || !props.loadAll) return
  loading.value = true
  try {
    internal.value = await props.loadAll()
    loaded.value = true
  } catch {
    internal.value = []
  } finally {
    loading.value = false
  }
}

watch(() => props.open, (isOpen) => {
  if (isOpen) void load()
}, {immediate: true})

// Плоский список с отступом по глубине — все разделы видны (как папки в редакторе).
interface FlatItem { cat: SectionCategory; depth: number }

const flat = computed<FlatItem[]>(() => {
  const childrenOf = new Map<string | null, SectionCategory[]>()
  const ids = new Set(categories.value.map(c => c.id))
  for (const c of categories.value) {
    // Раздел, чей родитель не загружен, показываем как корневой.
    const key = c.parent_id !== null && ids.has(c.parent_id) ? c.parent_id : null
    const arr = childrenOf.get(key) ?? []
    arr.push(c)
    childrenOf.set(key, arr)
  }
  const result: FlatItem[] = []
  const walk = (parentId: string | null, depth: number): void => {
    for (const cat of childrenOf.get(parentId) ?? []) {
      result.push({cat, depth})
      walk(cat.id, depth + 1)
    }
  }
  walk(null, 0)
  return result
})

const showLoader = computed(() => loading.value && !loaded.value && !props.items)

function toggleSelect(id: string): void {
  emit('update:modelValue', props.modelValue.includes(id)
      ? props.modelValue.filter(c => c !== id)
      : [...props.modelValue, id])
}
</script>

<template>
  <div class="max-h-48 overflow-y-auto rounded-md border">
    <div v-if="showLoader" class="flex items-center justify-center gap-2 py-6 text-sm text-muted-foreground">
      <Loader2 class="size-3.5 animate-spin"/>
      Загрузка разделов…
    </div>

    <div v-else-if="!flat.length" class="px-3 py-6 text-center text-sm text-muted-foreground">
      Разделов пока нет
    </div>

    <template v-else>
      <label
          v-for="item in flat"
          :key="item.cat.id"
          class="flex cursor-pointer items-center gap-2.5 border-b px-3 py-2 text-sm transition last:border-0 hover:bg-muted/40"
          :style="{ paddingLeft: `${12 + item.depth * 14}px` }"
          @click="toggleSelect(item.cat.id)"
      >
        <div
            class="flex size-4 flex-none items-center justify-center rounded border transition"
            :class="modelValue.includes(item.cat.id) ? 'border-primary bg-primary' : 'border-border bg-background'"
        >
          <Check v-if="modelValue.includes(item.cat.id)" class="size-2.5 text-primary-foreground"/>
        </div>
        <span
            class="select-none truncate"
            :class="modelValue.includes(item.cat.id) ? 'font-medium text-foreground' : 'text-muted-foreground'"
        >{{ item.cat.name }}</span>
      </label>
    </template>
  </div>
</template>
