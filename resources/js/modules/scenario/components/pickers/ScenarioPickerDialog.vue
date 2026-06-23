<script setup lang="ts">
import {computed, ref, toRef, watch} from 'vue'
import {Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle} from '@/components/ui/dialog'
import SearchInput from '@/components/SearchInput.vue'
import EmptyState from '@/components/EmptyState.vue'
import {Skeleton} from '@/components/ui/skeleton'
import {useScenarioFeed} from '@/modules/scenario/composables/useScenarioFeed'
import {formatDateTime} from '@/lib/formatters'
import {ChevronRight, Folder, Workflow} from 'lucide-vue-next'

const props = defineProps<{
  open: boolean
  excludeScenarioId?: string | null
}>()

const emit = defineEmits<{
  'update:open': [v: boolean]
  select: [scenario: { id: string; name: string }]
}>()

// Локальный стейт раздела/папки внутри диалога — не лезем в URL state страницы.
const activeFolder = ref<string>('all')
// Стек посещённых папок: [{id, name}, ...]. Корень не входит.
const trail = ref<{ id: string; name: string }[]>([])
const excludeRef = toRef(props, 'excludeScenarioId')
const excludeNullable = computed(() => excludeRef.value ?? null)
const {rows, meta, loading, page, search} = useScenarioFeed(activeFolder, {
  excludeScenarioId: excludeNullable,
  syncUrl: false,
})

// Сбрасываем папку и поиск при каждом открытии
watch(() => props.open, (open) => {
  if (open) {
    activeFolder.value = 'all'
    trail.value = []
    search.value = ''
    page.value = 1
  }
})

const STATUS_DOT: Record<'active' | 'draft' | 'archived', string> = {
  active: 'bg-emerald-500',
  draft: 'bg-slate-400',
  archived: 'bg-slate-300',
}

function enterFolder(id: string, name: string) {
  // Из глобального поиска переходить по папке: путь к ней не знаем,
  // показываем как единственную крошку.
  if (search.value.trim()) {
    search.value = ''
    trail.value = [{id, name}]
  } else {
    trail.value = [...trail.value, {id, name}]
  }
  activeFolder.value = id
  page.value = 1
}

// Переход на крошку по индексу. -1 = корень.
function goToCrumb(index: number) {
  search.value = ''
  if (index < 0) {
    trail.value = []
    activeFolder.value = 'all'
  } else {
    trail.value = trail.value.slice(0, index + 1)
    activeFolder.value = trail.value[index].id
  }
  page.value = 1
}

function pickScenario(id: string, name: string) {
  emit('select', {id, name})
  emit('update:open', false)
}
</script>

<template>
  <Dialog :open="props.open" @update:open="(v: boolean) => emit('update:open', v)">
    <DialogContent class="flex max-h-[85vh] w-[680px] max-w-[95vw] flex-col p-0">
      <DialogHeader class="shrink-0 border-b border-slate-100 px-6 py-4">
        <DialogTitle class="text-[15px] font-semibold">Выбор сценария</DialogTitle>
        <DialogDescription class="sr-only">Выбор сценария для перехода</DialogDescription>
      </DialogHeader>

      <!-- Breadcrumbs -->
      <nav class="shrink-0 flex flex-wrap items-center gap-1 border-b border-slate-100 px-6 py-2.5 text-[12.5px]">
        <button
            class="font-medium transition-colors"
            :class="trail.length ? 'text-slate-500 hover:text-slate-900' : 'text-slate-900'"
            @click="goToCrumb(-1)"
        >
          Сценарии
        </button>
        <template v-for="(crumb, i) in trail" :key="crumb.id">
          <ChevronRight :size="12" class="text-slate-300"/>
          <button
              class="truncate transition-colors"
              :class="i === trail.length - 1 ? 'font-medium text-slate-900' : 'text-slate-500 hover:text-slate-900'"
              @click="goToCrumb(i)"
          >
            {{ crumb.name }}
          </button>
        </template>
      </nav>

      <div class="shrink-0 px-6 pb-3 pt-3">
        <SearchInput v-model="search" placeholder="Поиск по сценариям..."/>
      </div>

      <div class="flex min-h-0 flex-1 flex-col px-6 pb-4">
        <!-- Loading -->
        <div v-if="loading && !rows.length" class="flex flex-col gap-1.5">
          <Skeleton v-for="i in 6" :key="i" class="h-12 w-full rounded-lg"/>
        </div>

        <EmptyState
            v-else-if="!rows.length"
            title="Здесь пусто"
            :subtitle="search ? 'Ничего не найдено' : (activeFolder === 'all' ? 'Нет доступных сценариев' : 'Нет сценариев в разделе')"
        >
          <template #icon>
            <Workflow :size="20"/>
          </template>
        </EmptyState>

        <div v-else class="flex min-h-0 flex-1 flex-col gap-0.5 overflow-y-auto">
          <template v-for="row in rows" :key="`${row.type}-${row.id}`">
            <!-- Folder -->
            <button
                v-if="row.type === 'folder'"
                class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-left hover:bg-slate-50"
                @click="enterFolder(row.id, row.name)"
            >
              <div class="flex h-8 w-8 flex-none items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                <Folder :size="14"/>
              </div>
              <div class="min-w-0 flex-1">
                <div class="truncate text-[13.5px] font-semibold text-slate-900 group-hover:text-blue-600">
                  {{ row.name }}
                </div>
                <div class="mt-0.5 text-[11.5px] text-slate-400">
                  <template v-if="row.children_count">{{ row.children_count }} подпапок</template>
                  <template v-else>раздел</template>
                </div>
              </div>
              <ChevronRight :size="14" class="flex-none text-slate-300"/>
            </button>

            <!-- Scenario -->
            <button
                v-else
                class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-left hover:bg-blue-50"
                @click="pickScenario(row.id, row.name)"
            >
              <div class="flex h-8 w-8 flex-none items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                <Workflow :size="14"/>
              </div>
              <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                  <span class="inline-block h-2 w-2 flex-none rounded-full" :class="STATUS_DOT[row.status]"/>
                  <span class="truncate text-[13.5px] font-semibold text-slate-900 group-hover:text-blue-700">
                    {{ row.name }}
                  </span>
                  <span v-if="row.alias"
                        class="hidden flex-none rounded border border-slate-200 bg-white px-1.5 py-0.5 font-mono text-[10.5px] text-slate-400 lg:inline-flex">
                    @{{ row.alias }}
                  </span>
                </div>
                <p v-if="row.description" class="mt-0.5 truncate text-[11.5px] text-slate-500">{{ row.description }}</p>
                <div v-if="row.updated_at" class="mt-0.5 text-[11px] text-slate-400">
                  Обновлён: {{ formatDateTime(row.updated_at) }}
                </div>
              </div>
              <ChevronRight :size="14" class="flex-none text-slate-300"/>
            </button>
          </template>
        </div>

        <!-- Pagination -->
        <div v-if="meta.last_page > 1" class="mt-3 flex items-center justify-end gap-1 border-t border-slate-100 pt-3">
          <button
              class="flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition disabled:opacity-30 hover:enabled:bg-slate-50"
              :disabled="meta.current_page <= 1"
              @click="page = meta.current_page - 1"
          >
            <ChevronRight :size="12" class="rotate-180"/>
          </button>
          <span class="px-2 text-[12px] tabular-nums text-slate-500">{{ meta.current_page }} / {{
              meta.last_page
            }}</span>
          <button
              class="flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition disabled:opacity-30 hover:enabled:bg-slate-50"
              :disabled="meta.current_page >= meta.last_page"
              @click="page = meta.current_page + 1"
          >
            <ChevronRight :size="12"/>
          </button>
        </div>
      </div>
    </DialogContent>
  </Dialog>
</template>
