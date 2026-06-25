<script setup lang="ts">
import {computed, ref, watch} from 'vue'
import {ChevronRight, Folder, FolderOpen, Loader2} from 'lucide-vue-next'
import {actionCategoryRepository} from '@/modules/actions/repositories/actionCategoryRepository'
import type {ActionCategory} from '@/modules/actions/types/action'

interface SectionNode extends ActionCategory {
  children: SectionNode[]
}

const props = withDefaults(defineProps<{
  modelValue: string[]
  // Перезагружать дерево при открытии (чтобы видеть только что созданные разделы).
  open?: boolean
}>(), {open: true})

const emit = defineEmits<{ 'update:modelValue': [value: string[]] }>()

const categories = ref<ActionCategory[]>([])
const loading = ref(false)
const loaded = ref(false)
const expanded = ref(new Set<string>())

async function load(): Promise<void> {
  loading.value = true
  try {
    categories.value = await actionCategoryRepository.all()
    loaded.value = true
  } catch {
    categories.value = []
  } finally {
    loading.value = false
  }
}

// Грузим при первом открытии и обновляем при последующих (cheap — backend кэширует).
watch(() => props.open, (isOpen) => {
  if (isOpen) void load()
}, {immediate: true})

const tree = computed<SectionNode[]>(() => {
  const map = new Map<string, SectionNode>()
  const roots: SectionNode[] = []
  for (const c of categories.value) map.set(c.id, {...c, children: []})
  for (const c of categories.value) {
    const node = map.get(c.id)!
    if (c.parent_id !== null && map.has(c.parent_id)) map.get(c.parent_id)!.children.push(node)
    else roots.push(node)
  }
  return roots
})

interface FlatItem { node: SectionNode; depth: number }

const flat = computed<FlatItem[]>(() => {
  const result: FlatItem[] = []
  const walk = (nodes: SectionNode[], depth: number): void => {
    for (const n of nodes) {
      result.push({node: n, depth})
      if (n.children.length && expanded.value.has(n.id)) walk(n.children, depth + 1)
    }
  }
  walk(tree.value, 0)
  return result
})

function toggleExpand(id: string): void {
  const next = new Set(expanded.value)
  if (next.has(id)) next.delete(id)
  else next.add(id)
  expanded.value = next
}

function toggleSelect(id: string): void {
  emit('update:modelValue', props.modelValue.includes(id)
      ? props.modelValue.filter(c => c !== id)
      : [...props.modelValue, id])
}
</script>

<template>
  <div class="rounded-lg border border-slate-200">
    <div v-if="loading && !loaded" class="flex items-center justify-center gap-2 py-6 text-[13px] text-slate-400">
      <Loader2 :size="14" class="animate-spin"/>
      Загрузка разделов…
    </div>

    <div v-else-if="flat.length === 0" class="px-3 py-6 text-center text-[13px] text-slate-400">
      Разделов пока нет
    </div>

    <div v-else class="max-h-48 overflow-y-auto p-1">
      <div
          v-for="item in flat"
          :key="item.node.id"
          class="flex items-center gap-1.5 rounded-md py-1 pr-2 transition hover:bg-slate-50"
          :style="{ paddingLeft: `${4 + item.depth * 16}px` }"
      >
        <button
            v-if="item.node.children.length"
            type="button"
            class="inline-flex size-5 flex-none items-center justify-center rounded text-slate-400 transition-transform hover:text-slate-700"
            :class="expanded.has(item.node.id) ? 'rotate-90' : ''"
            @click="toggleExpand(item.node.id)"
        >
          <ChevronRight :size="12"/>
        </button>
        <span v-else class="inline-block w-5 flex-none"/>

        <label class="flex min-w-0 flex-1 cursor-pointer items-center gap-2 text-[13px] text-slate-700">
          <input
              type="checkbox"
              class="size-3.5 flex-none rounded border-slate-300"
              :checked="modelValue.includes(item.node.id)"
              @change="toggleSelect(item.node.id)"
          />
          <span class="flex-none text-slate-400">
            <FolderOpen v-if="expanded.has(item.node.id) && item.node.children.length" :size="14"/>
            <Folder v-else :size="14"/>
          </span>
          <span class="truncate">{{ item.node.name }}</span>
        </label>
      </div>
    </div>
  </div>
</template>
