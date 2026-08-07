<script setup lang="ts">
import SearchInput from '@/components/SearchInput.vue'
import {Link} from '@inertiajs/vue3'
import {ChevronRight, Folder, FolderOpen, Loader2} from 'lucide-vue-next'

interface ScenarioItem {
  id: string
  name: string
  status: 'active' | 'draft' | 'archived'
}

interface FolderNode {
  id: string
  name: string
}

interface SearchScenarioResult extends ScenarioItem {
  folderId: string | null
}

interface FlatTreeItem {
  type: 'folder' | 'scenario'
  id: string
  depth: number
  hasChildren: boolean
  loading?: boolean
  folder?: FolderNode
  scenario?: ScenarioItem
}

defineProps<{
  searchQuery: string
  isSearchMode: boolean
  searchLoading: boolean
  rootLoading: boolean
  hasWorkspace: boolean
  searchFolderResults: FolderNode[]
  searchScenarios: SearchScenarioResult[]
  sidebarTreeItems: FlatTreeItem[]
  expandedIds: Set<string>
  selectedScenarioId: string | null
  pathFor: (id: string | null | undefined) => string
  parentPathFor: (id: string | null | undefined) => string
  highlight: (text: string, query: string) => string
}>()

defineEmits<{
  'update:searchQuery': [v: string]
  pickScenario: [id: string]
  toggleExpand: [id: string]
  jumpToFolder: [id: string]
}>()

const STATUS_DOT: Record<string, string> = {
  active: 'bg-emerald-500',
  draft: 'bg-slate-400',
  archived: 'bg-slate-300',
}
</script>

<template>
  <aside class="flex w-72 flex-none flex-col overflow-hidden border-r border-slate-200 bg-white">
    <div class="shrink-0 px-3 pt-3 pb-2">
      <div class="flex rounded-lg bg-slate-100 p-0.5">
        <Link
            :href="route('workspace.scenarios')"
            class="flex-1 rounded-md bg-white py-1.5 text-center text-[12px] font-semibold text-slate-900 shadow-sm"
        >Сценарии
        </Link>
        <Link
            :href="route('workspace')"
            class="flex-1 rounded-md py-1.5 text-center text-[12px] font-semibold text-slate-500 transition-all hover:text-slate-700"
        >Опросы
        </Link>
      </div>
    </div>

    <div class="shrink-0 px-3 pb-2">
      <SearchInput :model-value="searchQuery" placeholder="Поиск разделов и сценариев…"
                   @update:model-value="$emit('update:searchQuery', $event)"/>
    </div>

    <div class="flex-1 overflow-y-auto px-3 pt-1 pb-3">
      <template v-if="isSearchMode">
        <div v-if="searchLoading" class="flex items-center justify-center py-4 text-slate-400">
          <Loader2 :size="14" class="animate-spin"/>
        </div>
        <div v-else-if="!searchFolderResults.length && !searchScenarios.length"
             class="px-3 py-2 text-[12px] text-slate-400">
          Ничего не найдено
        </div>
        <div v-else class="flex flex-col gap-0.5">
          <button
              v-for="folder in searchFolderResults"
              :key="`sf-${folder.id}`"
              class="group flex w-full items-start gap-2 rounded-lg px-2 py-1.5 text-left text-[13px] text-slate-700 transition-colors hover:bg-slate-50"
              @click="$emit('jumpToFolder', folder.id)"
          >
                        <span class="mt-0.5 flex-none text-slate-400">
                            <Folder :size="14"/>
                        </span>
            <span class="min-w-0 flex-1">
                            <!-- eslint-disable-next-line vue/no-v-html -->
                            <span class="block truncate" v-html="highlight(folder.name, searchQuery)"/>
                            <span v-if="parentPathFor(folder.id)"
                                  class="mt-0.5 block truncate text-[11px] text-slate-400">
                                {{ parentPathFor(folder.id) }}
                            </span>
                        </span>
          </button>

          <button
              v-for="s in searchScenarios"
              :key="`ss-${s.id}`"
              class="group flex w-full items-start gap-2 rounded-lg px-2 py-1.5 text-left text-[12.5px] transition-colors"
              :class="selectedScenarioId === s.id
                            ? 'bg-blue-50 font-semibold text-blue-700'
                            : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
              :disabled="s.status !== 'active'"
              @click="$emit('pickScenario', s.id)"
          >
            <span class="mt-1.5 inline-block h-2 w-2 flex-none rounded-full" :class="STATUS_DOT[s.status]"/>
            <span class="min-w-0 flex-1">
                            <!-- eslint-disable-next-line vue/no-v-html -->
                            <span class="block truncate" v-html="highlight(s.name, searchQuery)"/>
                            <span v-if="pathFor(s.folderId) || !s.folderId"
                                  class="mt-0.5 block truncate text-[11px] text-slate-400">
                                {{ pathFor(s.folderId) || 'Без раздела' }}
                            </span>
                        </span>
          </button>
        </div>
      </template>

      <template v-else>
        <div v-if="rootLoading" class="flex items-center justify-center py-4 text-slate-400">
          <Loader2 :size="14" class="animate-spin"/>
        </div>
        <div v-else class="flex flex-col gap-0.5">
          <template v-for="item in sidebarTreeItems" :key="`${item.type}-${item.id}`">
            <button
                v-if="item.type === 'folder'"
                class="group flex h-8 w-full items-center rounded-lg pr-2 text-[13px] text-slate-700 transition-colors hover:bg-slate-50"
                :style="{ paddingLeft: `${6 + item.depth * 14}px` }"
                @click="$emit('toggleExpand', item.id)"
            >
                            <span v-if="item.loading"
                                  class="inline-flex h-5 w-5 flex-none items-center justify-center text-slate-400">
                                <Loader2 :size="12" class="animate-spin"/>
                            </span>
              <span
                  v-else-if="item.hasChildren"
                  class="inline-flex h-5 w-5 flex-none items-center justify-center rounded transition-transform text-slate-400 group-hover:text-slate-700"
                  :class="expandedIds.has(item.id) ? 'rotate-90' : ''"
              >
                                <ChevronRight :size="12"/>
                            </span>
              <span v-else class="inline-block w-5 flex-none"/>
              <span class="mr-2 flex-none text-slate-400">
                                <FolderOpen v-if="expandedIds.has(item.id)" :size="14"/>
                                <Folder v-else :size="14"/>
                            </span>
              <span class="min-w-0 flex-1 truncate text-left">{{ item.folder?.name }}</span>
            </button>

            <button
                v-else
                class="group flex h-7 w-full items-center rounded-lg pr-2 text-[12.5px] transition-colors"
                :class="selectedScenarioId === item.id
                                ? 'bg-blue-50 font-semibold text-blue-700'
                                : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
                :style="{ paddingLeft: `${6 + item.depth * 14 + 20}px` }"
                :disabled="item.scenario!.status !== 'active'"
                @click="$emit('pickScenario', item.scenario!.id)"
            >
              <span class="mr-2 inline-block h-2 w-2 flex-none rounded-full"
                    :class="STATUS_DOT[item.scenario!.status]"/>
              <span class="min-w-0 flex-1 truncate text-left">{{ item.scenario?.name }}</span>
            </button>
          </template>

          <div v-if="!hasWorkspace" class="px-3 py-2 text-[12px] leading-relaxed text-slate-400">
            Рабочая папка не настроена. Откройте раздел сценариев и включите «Использовать как рабочую папку».
          </div>
          <div v-else-if="sidebarTreeItems.length === 0" class="px-3 py-2 text-[12px] text-slate-400">
            Сценариев нет
          </div>
        </div>
      </template>
    </div>
  </aside>
</template>
