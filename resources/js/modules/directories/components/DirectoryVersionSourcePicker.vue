<script setup lang="ts">
import {ref, watch} from 'vue'
import {Loader2} from 'lucide-vue-next'
import type {DirectoryVersion, SourceType} from '@/modules/directories/types/directory'
import type {DirectoryVersionsContext} from '@/modules/directories/composables/useDirectoryVersions'
import {SOURCE_TYPES} from '@/modules/directories/sourceTypes'

const props = defineProps<{
  currentVersion: DirectoryVersion
  versCtx: DirectoryVersionsContext
  canManage: boolean
}>()

const emit = defineEmits<{ change: [type: SourceType] }>()

// ── «Другой» (fallback option) ─────────────────────────────────────────────────
const allowOther = ref(props.currentVersion.allow_other)
const otherLabel = ref(props.currentVersion.other_label ?? '')

watch(() => props.currentVersion, (v) => {
  allowOther.value = v.allow_other
  otherLabel.value = v.other_label ?? ''
})

function saveOther(): void {
  void props.versCtx.saveVersionSettings(
      props.currentVersion.id,
      props.currentVersion.source_type,
      props.currentVersion.sync_options ?? undefined,
      {allow_other: allowOther.value, other_label: otherLabel.value.trim() || null},
  )
}

function toggleOther(): void {
  allowOther.value = !allowOther.value
  saveOther()
}
</script>

<template>
  <div class="rounded-xl border border-border/60 bg-card">
    <div class="border-b border-border/60 px-6 py-4">
      <div class="text-base font-semibold">Настройки версии</div>
      <div class="text-sm text-muted-foreground">Тип источника для v{{ currentVersion.version_number }}</div>
    </div>
    <div class="p-6 space-y-4">
      <div v-if="versCtx.settingsError.value"
           class="rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
        {{ versCtx.settingsError.value }}
      </div>
      <div class="grid grid-cols-3 gap-3">
        <button
            v-for="src in SOURCE_TYPES"
            :key="src.id"
            type="button"
            class="flex flex-col items-start gap-1 rounded-xl border p-4 text-left transition"
            :class="currentVersion.source_type === src.id
            ? 'border-primary bg-primary/5 ring-1 ring-primary/30'
            : 'border-border/60 hover:border-primary/40 hover:bg-muted/20'"
            :disabled="!canManage || versCtx.settingsSaving.value"
            @click="emit('change', src.id)"
        >
          <div class="flex w-full items-center justify-between gap-2">
            <span class="text-sm font-medium">{{ src.label }}</span>
            <Loader2 v-if="versCtx.settingsSaving.value" class="size-3.5 animate-spin text-muted-foreground"/>
            <div
                v-else
                class="flex size-4 shrink-0 items-center justify-center rounded-full border transition"
                :class="currentVersion.source_type === src.id ? 'border-primary bg-primary' : 'border-border/60'"
            >
              <div v-if="currentVersion.source_type === src.id" class="size-1.5 rounded-full bg-primary-foreground"/>
            </div>
          </div>
          <span class="text-xs text-muted-foreground">{{ src.description }}</span>
        </button>
      </div>

      <div class="space-y-3 border-t border-border/60 pt-4">
        <label class="flex cursor-pointer items-center gap-2.5">
          <input
              type="checkbox"
              class="size-4 rounded accent-blue-600"
              :checked="allowOther"
              :disabled="!canManage || versCtx.settingsSaving.value"
              @change="toggleOther"
          />
          <span class="text-sm font-medium">Вариант «Другой»</span>
        </label>
        <p class="text-xs text-muted-foreground">
          Добавляет в конец списка элемент-заглушку, если ни одно значение справочника не подходит.
        </p>

        <div v-if="allowOther" class="space-y-2">
          <label class="text-sm font-medium">Название варианта</label>
          <input
              v-model="otherLabel"
              type="text"
              placeholder="Другой"
              class="flex h-10 w-full rounded-lg border border-input bg-background px-3 text-sm sm:max-w-xs"
              :disabled="!canManage || versCtx.settingsSaving.value"
              @change="saveOther"
          />
        </div>
      </div>
    </div>
  </div>
</template>
