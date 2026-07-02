<script setup lang="ts" generic="T extends SectionCategory">
import {Skeleton} from '@/components/ui/skeleton'
import {
  ContextMenuContent, ContextMenuItem, ContextMenuRoot,
  ContextMenuSeparator, ContextMenuTrigger,
} from 'reka-ui'
import {ChevronRight, Folder, FolderOpen, Pencil, Trash2} from 'lucide-vue-next'
import type {Component} from 'vue'
import type {SectionCategory} from '@/types/section'
import type {SectionTree} from '@/composables/useSectionTree'

withDefaults(defineProps<{
  tree: SectionTree<T>
  activeFolder: string
  allLabel: string
  title: string
  titleIcon: Component
  allIcon: Component
  canEdit?: boolean
  canDelete?: boolean
}>(), {
  canEdit: true,
  canDelete: true,
})

const emit = defineEmits<{
  select: [id: string]
  editSection: [section: T]
  deleteSection: [section: T]
}>()
</script>

<template>
  <aside class="flex w-64 flex-none flex-col overflow-hidden border-r border-slate-200 bg-white">
    <div class="flex items-center gap-2.5 px-4 pb-3 pt-4">
      <div class="flex h-8 w-8 flex-none items-center justify-center rounded-lg bg-blue-50 text-blue-600">
        <component :is="titleIcon" :size="16"/>
      </div>
      <div class="min-w-0 leading-tight">
        <div class="truncate text-[13px] font-semibold text-slate-900">{{ title }}</div>
      </div>
    </div>

    <div v-if="$slots['top-links']" class="px-3 pb-3">
      <slot name="top-links"/>
    </div>
    <div v-if="$slots['top-links']" class="mx-3 border-t border-slate-100"/>

    <div class="flex-1 overflow-y-auto px-3 pt-3">
      <div class="flex flex-col gap-0.5">
        <button
            class="group flex h-8 w-full items-center rounded-lg pr-2 text-[13px] transition-colors"
            :class="activeFolder === 'all' ? 'bg-blue-50 font-semibold text-blue-700' : 'text-slate-700 hover:bg-slate-50'"
            style="padding-left: 6px"
            @click="emit('select', 'all')"
        >
          <span
              class="inline-flex h-5 w-5 flex-none items-center justify-center rounded transition-transform rotate-90"
              :class="activeFolder === 'all' ? 'text-blue-600' : 'text-slate-400 group-hover:text-slate-700'"
          >
            <ChevronRight :size="12"/>
          </span>
          <span class="mr-2 flex-none" :class="activeFolder === 'all' ? 'text-blue-600' : 'text-slate-400'">
            <component :is="allIcon" :size="14"/>
          </span>
          <span class="min-w-0 flex-1 truncate text-left">{{ allLabel }}</span>
        </button>

        <div v-if="tree.loading.value" class="flex flex-col gap-1 px-1 pl-5">
          <Skeleton v-for="i in 5" :key="i" class="h-8 w-full rounded-lg" :style="{ opacity: 1 - i * 0.15 }"/>
        </div>

        <ContextMenuRoot
            v-for="item in tree.sidebarItems.value"
            :key="`folder-${item.section.id}`"
        >
          <ContextMenuTrigger as-child>
            <button
                class="group flex h-8 w-full items-center rounded-lg pr-2 text-[13px] transition-colors"
                :class="activeFolder === item.section.id ? 'bg-blue-50 font-semibold text-blue-700' : 'text-slate-700 hover:bg-slate-50'"
                :style="{ paddingLeft: `${6 + (item.depth + 1) * 14}px` }"
                @click="emit('select', item.section.id)"
            >
              <span
                  v-if="item.hasChildren"
                  class="inline-flex h-5 w-5 flex-none items-center justify-center rounded transition-transform"
                  :class="[tree.expandedIds.value.has(item.section.id) ? 'rotate-90' : '', activeFolder === item.section.id ? 'text-blue-600' : 'text-slate-400 group-hover:text-slate-700']"
                  @click.stop="tree.toggleExpand(item.section.id)"
              >
                <ChevronRight :size="12"/>
              </span>
              <span v-else class="inline-block w-5 flex-none"/>

              <span class="mr-2 flex-none"
                    :class="activeFolder === item.section.id ? 'text-blue-600' : 'text-slate-400'">
                <FolderOpen v-if="tree.expandedIds.value.has(item.section.id) && item.hasChildren" :size="14"/>
                <Folder v-else :size="14"/>
              </span>

              <span class="min-w-0 flex-1 truncate text-left">{{ item.section.name }}</span>
              <span class="ml-2 tabular-nums text-[11px] font-semibold"
                    :class="activeFolder === item.section.id ? 'text-blue-700' : 'text-slate-400'">
                {{ tree.countInSection(item.section.id) }}
              </span>
            </button>
          </ContextMenuTrigger>

          <ContextMenuContent
              v-if="canEdit || canDelete"
              class="z-50 min-w-[140px] overflow-hidden rounded-xl border border-slate-200 bg-white p-1 shadow-lg">
            <ContextMenuItem
                v-if="canEdit"
                class="flex cursor-pointer items-center gap-2 rounded-lg px-3 py-1.5 text-[13px] text-slate-700 outline-none hover:bg-slate-50 focus:bg-slate-50"
                @select="emit('editSection', item.section)"
            >
              <Pencil :size="13" class="text-slate-400"/>
              Переименовать
            </ContextMenuItem>
            <template v-if="canDelete && !item.section.is_system">
              <ContextMenuSeparator v-if="canEdit" class="my-1 h-px bg-slate-100"/>
              <ContextMenuItem
                  class="flex cursor-pointer items-center gap-2 rounded-lg px-3 py-1.5 text-[13px] text-red-600 outline-none hover:bg-red-50 focus:bg-red-50"
                  @select="emit('deleteSection', item.section)"
              >
                <Trash2 :size="13"/>
                Удалить
              </ContextMenuItem>
            </template>
          </ContextMenuContent>
        </ContextMenuRoot>
      </div>
    </div>
  </aside>
</template>
