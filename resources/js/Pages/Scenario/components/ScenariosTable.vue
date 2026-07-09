<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue'
import {Skeleton} from '@/components/ui/skeleton'
import {
  DropdownMenu, DropdownMenuContent, DropdownMenuItem,
  DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  ChevronRight, Copy, Folder, FolderOpen, MoreHorizontal,
  Pencil, Play, Trash2, Workflow,
} from 'lucide-vue-next'
import type {
  FeedScenarioItem,
  FeedFolderItem,
  ScenarioFeedRow,
  ScenarioFeedPagination
} from '@/modules/scenario/repositories/scenarioFeedRepository'
import {formatDateTime} from '@/lib/formatters'
import {pluralRu} from '@/lib/pluralize'

defineProps<{
  rows: ScenarioFeedRow[]
  meta: ScenarioFeedPagination
  loading: boolean
  isSearchMode: boolean
  canManage: boolean
  canDelete: boolean
}>()

const emit = defineEmits<{
  selectFolder: [id: string]
  editSection: [row: FeedFolderItem]
  deleteSection: [row: FeedFolderItem]
  openScenario: [id: string]
  duplicateScenario: [id: string]
  playScenario: [id: string]
  deleteScenario: [s: FeedScenarioItem]
  pageChange: [page: number]
}>()

const STATUS_DOT: Record<'active' | 'draft' | 'archived', string> = {
  active: 'bg-emerald-500',
  draft: 'bg-slate-400',
  archived: 'bg-slate-300',
}

function actorLabel(actor: FeedScenarioItem['created_by']): string | null {
  if (!actor) return null
  return actor.fio ?? actor.name ?? actor.login ?? null
}
</script>

<template>
  <section>
    <!-- Skeleton -->
    <div v-if="loading && !rows.length" class="overflow-hidden rounded-xl border border-slate-200 bg-white">
      <div class="grid border-b border-slate-100 px-5 py-3" style="grid-template-columns: 1fr 160px 160px 108px">
        <Skeleton class="h-3 w-24"/>
        <Skeleton class="h-3 w-20"/>
        <Skeleton class="h-3 w-20"/>
        <Skeleton class="h-3 w-16"/>
      </div>
      <div
          v-for="i in 6"
          :key="i"
          class="grid items-center border-b border-slate-100 px-5 py-4 last:border-0"
          style="grid-template-columns: 1fr 160px 160px 108px"
      >
        <div class="flex items-center gap-3 pr-4">
          <Skeleton class="h-8 w-8 flex-none rounded-lg"/>
          <div class="min-w-0 flex-1 space-y-1.5">
            <Skeleton class="h-3.5 w-40 rounded"/>
            <Skeleton class="h-3 w-24 rounded"/>
          </div>
        </div>
        <Skeleton class="h-3 w-24 rounded"/>
        <Skeleton class="h-3 w-24 rounded"/>
        <Skeleton class="h-7 w-7 rounded-full justify-self-end"/>
      </div>
    </div>

    <EmptyState
        v-else-if="!rows.length"
        :title="isSearchMode ? 'Ничего не найдено' : 'Здесь пусто'"
        :subtitle="isSearchMode ? 'Попробуйте изменить запрос' : 'Создайте раздел или сценарий, чтобы начать'"
    >
      <template #icon>
        <Workflow :size="22"/>
      </template>
    </EmptyState>

    <template v-else>
      <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <div
            class="grid border-b border-slate-100 px-5 py-3 text-[11px] font-bold uppercase tracking-wider text-slate-400"
            style="grid-template-columns: 1fr 160px 160px 108px"
        >
          <div>Название</div>
          <div>Создано</div>
          <div>Отредактировано</div>
          <div class="text-right">Действия</div>
        </div>

        <template v-for="(row, idx) in rows" :key="`${row.type}-${row.id}`">
          <!-- Folder row -->
          <div
              v-if="row.type === 'folder'"
              class="group grid items-center px-5 py-3.5 transition-colors hover:bg-slate-50/70 cursor-pointer"
              :class="idx < rows.length - 1 ? 'border-b border-slate-100' : ''"
              style="grid-template-columns: 1fr 160px 160px 108px"
              @click="emit('selectFolder', row.id)"
          >
            <div class="flex min-w-0 items-center gap-3 pr-4">
              <div class="flex h-8 w-8 flex-none items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                <Folder :size="15"/>
              </div>
              <div class="min-w-0 flex-1">
                <div
                    class="truncate text-[14px] font-semibold text-slate-900 group-hover:text-blue-600 transition-colors">
                  {{ row.name }}
                </div>
                <div class="mt-0.5 text-[11.5px] text-slate-400">
                  <template v-if="row.children_count">{{ row.children_count }}
                    {{ pluralRu(row.children_count, ['подпапка', 'подпапки', 'подпапок']) }}
                  </template>
                  <template v-else>раздел</template>
                </div>
              </div>
            </div>

            <div class="text-[12.5px] text-slate-500 tabular-nums">{{ formatDateTime(row.created_at) }}</div>
            <div class="text-[12.5px] text-slate-500 tabular-nums">{{ formatDateTime(row.updated_at) }}</div>

            <div class="flex justify-end" @click.stop>
              <DropdownMenu v-if="canManage || canDelete">
                <DropdownMenuTrigger as-child>
                  <button
                      class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700">
                    <MoreHorizontal :size="15"/>
                  </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-44">
                  <DropdownMenuItem class="cursor-pointer" @click="emit('selectFolder', row.id)">
                    <FolderOpen class="mr-2 h-4 w-4 text-slate-400"/>
                    Открыть
                  </DropdownMenuItem>
                  <DropdownMenuItem v-if="canManage" class="cursor-pointer" @click="emit('editSection', row)">
                    <Pencil class="mr-2 h-4 w-4 text-slate-400"/>
                    Редактировать
                  </DropdownMenuItem>
                  <DropdownMenuSeparator v-if="canDelete"/>
                  <DropdownMenuItem v-if="canDelete"
                                    class="cursor-pointer text-red-600 focus:bg-red-50 focus:text-red-600"
                                    @click="emit('deleteSection', row)">
                    <Trash2 class="mr-2 h-4 w-4"/>
                    Удалить
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
          </div>

          <!-- Scenario row -->
          <div
              v-else
              class="group grid items-center px-5 py-4 transition-colors hover:bg-slate-50/70"
              :class="idx < rows.length - 1 ? 'border-b border-slate-100' : ''"
              style="grid-template-columns: 1fr 160px 160px 108px"
          >
            <div class="flex min-w-0 items-start gap-3 pr-4">
              <div class="flex h-8 w-8 flex-none items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                <Workflow :size="15"/>
              </div>
              <div class="min-w-0 flex-1">
                <div class="flex min-w-0 items-center gap-2">
                                    <span
                                        class="inline-block h-2 w-2 flex-none rounded-full"
                                        :class="STATUS_DOT[row.status]"
                                        :title="row.status === 'active' ? 'Активный' : row.status === 'draft' ? 'Черновик' : 'Архив'"
                                    />
                  <span
                      class="truncate text-[14px] font-semibold text-slate-900 cursor-pointer hover:text-blue-600 transition-colors"
                      @click="emit('openScenario', row.id)"
                  >
                                        {{ row.name }}
                                    </span>
                  <span v-if="row.alias"
                        class="hidden flex-none rounded border border-slate-200 bg-slate-50 px-1.5 py-0.5 font-mono text-[11px] text-slate-400 lg:inline-flex">
                                        @{{ row.alias }}
                                    </span>
                </div>
                <div v-if="row.tags.length" class="mt-1 hidden flex-wrap items-center gap-1 lg:flex">
                                    <span
                                        v-for="t in row.tags"
                                        :key="t"
                                        class="inline-flex h-5 items-center rounded-full bg-slate-100 px-2 text-[10.5px] font-medium text-slate-600"
                                    >
                                        #{{ t }}
                                    </span>
                </div>
                <p v-if="row.description" class="mt-0.5 truncate text-[12.5px] text-slate-500">{{ row.description }}</p>
              </div>
            </div>

            <div class="min-w-0 pr-2">
              <div class="text-[12.5px] text-slate-500 tabular-nums">{{ formatDateTime(row.created_at) }}</div>
              <div v-if="actorLabel(row.created_by)" class="truncate text-[11px] text-slate-400">
                {{ actorLabel(row.created_by) }}
              </div>
            </div>

            <div class="min-w-0 pr-2">
              <div class="text-[12.5px] text-slate-500 tabular-nums">{{ formatDateTime(row.updated_at) }}</div>
              <div v-if="actorLabel(row.updated_by)" class="truncate text-[11px] text-slate-400">
                {{ actorLabel(row.updated_by) }}
              </div>
            </div>

            <div class="flex justify-end">
              <DropdownMenu v-if="canManage || canDelete">
                <DropdownMenuTrigger as-child>
                  <button
                      class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700">
                    <MoreHorizontal :size="15"/>
                  </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-44">
                  <DropdownMenuItem
                      v-if="row.status === 'active'"
                      class="cursor-pointer"
                      @click="emit('playScenario', row.id)"
                  >
                    <Play class="mr-2 h-4 w-4 text-blue-600"/>
                    Запустить
                  </DropdownMenuItem>
                  <DropdownMenuItem v-if="canManage" class="cursor-pointer" @click="emit('openScenario', row.id)">
                    <Pencil class="mr-2 h-4 w-4 text-slate-400"/>
                    Редактировать
                  </DropdownMenuItem>
                  <DropdownMenuItem v-if="canManage" class="cursor-pointer" @click="emit('duplicateScenario', row.id)">
                    <Copy class="mr-2 h-4 w-4 text-slate-400"/>
                    Дублировать
                  </DropdownMenuItem>
                  <DropdownMenuSeparator v-if="canManage && canDelete"/>
                  <DropdownMenuItem v-if="canDelete"
                                    class="cursor-pointer text-red-600 focus:bg-red-50 focus:text-red-600"
                                    @click="emit('deleteScenario', row)">
                    <Trash2 class="mr-2 h-4 w-4"/>
                    Удалить
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
          </div>
        </template>
      </div>

      <!-- Pagination -->
      <div v-if="meta.last_page > 1" class="mt-5 flex items-center justify-between">
                <span class="text-[12px] text-slate-400">
                    Страница {{ meta.current_page }} из {{ meta.last_page }} · {{ meta.total }}
                </span>
        <div class="flex items-center gap-1">
          <button
              class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition disabled:opacity-30 hover:enabled:bg-slate-50"
              :disabled="meta.current_page <= 1"
              @click="emit('pageChange', meta.current_page - 1)"
          >
            <ChevronRight :size="14" class="rotate-180"/>
          </button>
          <button
              v-for="p in meta.last_page"
              :key="p"
              class="flex h-8 w-8 items-center justify-center rounded-lg border text-[13px] font-medium transition"
              :class="p === meta.current_page
                            ? 'border-blue-500 bg-blue-50 text-blue-700'
                            : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50'"
              @click="emit('pageChange', p)"
          >
            {{ p }}
          </button>
          <button
              class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition disabled:opacity-30 hover:enabled:bg-slate-50"
              :disabled="meta.current_page >= meta.last_page"
              @click="emit('pageChange', meta.current_page + 1)"
          >
            <ChevronRight :size="14"/>
          </button>
        </div>
      </div>
    </template>
  </section>
</template>
