<script setup lang="ts">
/* eslint-disable vue/no-mutating-props -- composable context objects passed as props, mutations are on reactive refs inside */
import PageTabs from '@/components/PageTabs.vue'
import DirectoryItemsTable from './DirectoryItemsTable.vue'
import DirectoryImportHistory from './DirectoryImportHistory.vue'
import DirectorySchemaTab from './DirectorySchemaTab.vue'
import DirectorySettingsApi from './DirectorySettingsApi.vue'
import DirectoryUploadApi from './DirectoryUploadApi.vue'
import DirectoryVersionSourcePicker from './DirectoryVersionSourcePicker.vue'
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog'
import {Button} from '@/components/ui/button'
import {CheckCircle2, History, LayoutList, Loader2, RefreshCw, Settings, Table2, Upload} from 'lucide-vue-next'
import {computed, onMounted, reactive, ref, watch} from 'vue'
import {useDirectoryImport} from '@/modules/directories/composables/useDirectoryImport'
import {useDirectoryProxyPicker} from '@/modules/directories/composables/useDirectoryProxyPicker'
import {CRON_PRESETS, useDirectorySyncSchedule} from '@/modules/directories/composables/useDirectorySyncSchedule'
import type {DirectoryVersion, DirectoryVersionSyncOptions, SourceType} from '@/modules/directories/types/directory'
import type {DirectoryDetailContext} from '@/modules/directories/composables/useDirectoryDetail'
import type {DirectoryVersionsContext} from '@/modules/directories/composables/useDirectoryVersions'

const props = defineProps<{
  directoryId: string
  versionId: number
  currentVersion: DirectoryVersion | null
  detail: DirectoryDetailContext
  versCtx: DirectoryVersionsContext
  canManage: boolean
  canDelete: boolean
}>()

const emit = defineEmits<{ changeSourceType: [type: SourceType] }>()

const impCtx = useDirectoryImport(props.directoryId)
const proxyPicker = useDirectoryProxyPicker(
    () => props.detail.meta.proxy_uuid,
    (uuid) => {
      props.detail.meta.proxy_uuid = uuid
    },
)

const activeTab = ref<'items' | 'import' | 'schema' | 'settings'>('items')
const importSubTab = ref<'upload' | 'history' | 'settings'>('upload')

const syncOpts = reactive<DirectoryVersionSyncOptions>({add_new: true, update_existing: true, delete_unused: false})

const schedule = useDirectorySyncSchedule(props.directoryId)

function fmtDateTime(iso: string | null): string {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('ru-RU', {dateStyle: 'short', timeStyle: 'short'})
}

const schemaFields = computed(() =>
    (props.currentVersion?.schema_json ?? props.detail.meta.fields).filter(f => f.key !== ''),
)

watch(() => props.detail.meta.match_by, v => {
  props.versCtx.editableMatchBy.value = v ?? ''
})
watch(() => props.detail.meta.default_sort, v => {
  props.versCtx.editableDefaultSort.value = v ?? ''
})
watch(() => props.currentVersion, v => {
  props.versCtx.syncSchemaFrom(v, props.detail.meta.match_by, props.detail.meta.default_sort)
  if (v?.sync_options) {
    syncOpts.add_new = v.sync_options.add_new
    syncOpts.update_existing = v.sync_options.update_existing
    syncOpts.delete_unused = v.sync_options.delete_unused
  }
}, {immediate: true})

watch(props.detail.directory, dir => {
  if (dir?.import_settings) impCtx.restoreImportOptions(dir.import_settings)
}, {once: true})

const itemsTableRef = ref<InstanceType<typeof DirectoryItemsTable> | null>(null)
const historyTabRef = ref<InstanceType<typeof DirectoryImportHistory> | null>(null)

async function saveSchemaAndReload(): Promise<void> {
  await props.versCtx.saveSchema(props.versionId)
  await itemsTableRef.value?.clearFiltersAndReload()
}

async function saveApiSettings(): Promise<void> {
  if (!props.currentVersion) return
  await Promise.all([
    props.detail.save(),
    props.versCtx.saveVersionSettings(props.versionId, props.currentVersion.source_type, {...syncOpts}),
  ])
}

async function doRunSync(): Promise<void> {
  await impCtx.runSync({...syncOpts})
  await historyTabRef.value?.reload()
}

onMounted(() => {
  void impCtx.loadImports()
})
</script>

<template>
  <div class="space-y-4">
    <PageTabs
        v-model="activeTab"
        :tabs="[
        { id: 'items', label: 'Данные', icon: Table2 },
        { id: 'import', label: 'Импорт', icon: Upload },
        { id: 'schema', label: 'Схема', icon: LayoutList },
        { id: 'settings', label: 'Настройки', icon: Settings },
      ]"
    />

    <!-- Данные -->
    <DirectoryItemsTable
        v-if="activeTab === 'items'"
        ref="itemsTableRef"
        :directory-id="directoryId"
        :version-id="versionId"
        :schema-fields="schemaFields"
        :can-manage="canManage"
        :can-delete="canDelete"
        :default-sort="detail.meta.default_sort"
    />

    <!-- Импорт -->
    <template v-else-if="activeTab === 'import'">
      <nav class="flex flex-wrap gap-1.5">
        <button
            v-for="sub in [
            { id: 'upload', label: 'Синхронизация', icon: Upload },
            { id: 'history', label: 'История', icon: History },
            { id: 'settings', label: 'Настройки', icon: Settings },
          ]"
            :key="sub.id" type="button"
            class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium transition"
            :class="importSubTab === sub.id
            ? 'border-primary/50 bg-primary/10 text-primary'
            : 'border-border/60 bg-background text-muted-foreground hover:border-primary/30 hover:text-foreground'"
            @click="importSubTab = sub.id as typeof importSubTab"
        >
          <component :is="sub.icon" class="size-3"/>
          {{ sub.label }}
        </button>
      </nav>

      <DirectoryUploadApi
          v-if="importSubTab === 'upload'"
          :proxy-uuid="detail.meta.proxy_uuid"
          @open-sync="impCtx.openSync()"
      />

      <DirectoryImportHistory
          v-else-if="importSubTab === 'history'"
          ref="historyTabRef"
          :directory-id="directoryId"
          :version-id="versionId"
      />

      <div v-else-if="importSubTab === 'settings'" class="rounded-xl border border-border/60 bg-card">
        <div class="border-b border-border/60 px-6 py-4">
          <div class="text-base font-semibold">Настройки API-синхронизации</div>
          <div class="text-sm text-muted-foreground">Прокси, сопоставление полей и режим синхронизации</div>
        </div>
        <div class="space-y-6 p-6">
          <div v-if="detail.saveError.value || versCtx.settingsError.value"
               class="rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
            {{ detail.saveError.value || versCtx.settingsError.value }}
          </div>
          <DirectorySettingsApi
              v-model:external-key-field="detail.meta.external_key_field"
              :schema-fields="schemaFields"
              :field-mapping="detail.meta.field_mapping"
              :proxy-picker="proxyPicker"
              :sync-opts="syncOpts"
              :saving="detail.saving.value || versCtx.settingsSaving.value"
              :save-error="null"
              :can-manage="canManage"
              show-sync-options
              @save="saveApiSettings()"
          />
        </div>
      </div>

      <!-- Расписание (cron) -->
      <div v-if="importSubTab === 'settings'" class="rounded-xl border border-border/60 bg-card">
        <div class="border-b border-border/60 px-6 py-4">
          <div class="text-base font-semibold">Расписание синхронизации (cron)</div>
          <div class="text-sm text-muted-foreground">Автоматический запуск через планировщик Actions</div>
        </div>
        <div class="space-y-4 p-6">
          <label class="flex cursor-pointer items-center gap-2.5">
            <input
                v-model="schedule.enabled.value"
                type="checkbox"
                class="size-4 rounded accent-blue-600"
                :disabled="!canManage"
            />
            <span class="text-sm font-medium">Включить расписание</span>
          </label>

          <div class="space-y-2">
            <label class="text-sm font-medium">Cron-выражение</label>
            <input
                v-model="schedule.cron.value"
                type="text"
                placeholder="0 */6 * * *"
                class="flex h-10 w-full rounded-lg border border-input bg-background px-3 font-mono text-sm sm:max-w-xs"
                :disabled="!canManage || !schedule.enabled.value"
            />
            <div class="flex flex-wrap gap-1.5">
              <button
                  v-for="preset in CRON_PRESETS"
                  :key="preset.cron"
                  type="button"
                  class="rounded-lg border border-border/60 px-2.5 py-1 text-xs text-muted-foreground transition hover:border-primary/40 hover:text-foreground disabled:opacity-50"
                  :disabled="!canManage || !schedule.enabled.value"
                  @click="schedule.cron.value = preset.cron"
              >
                {{ preset.label }}
              </button>
            </div>
          </div>

          <div v-if="schedule.nextRunAt.value" class="text-xs text-muted-foreground">
            Следующий запуск: <span class="font-medium text-foreground">{{
              fmtDateTime(schedule.nextRunAt.value)
            }}</span>
            <template v-if="schedule.lastRunAt.value"> · последний: {{
                fmtDateTime(schedule.lastRunAt.value)
              }}
            </template>
          </div>

          <div v-if="schedule.error.value"
               class="rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
            {{ schedule.error.value }}
          </div>

          <Button v-if="canManage" :disabled="schedule.saving.value" class="gap-2 self-start" @click="schedule.save()">
            <Loader2 v-if="schedule.saving.value" class="size-4 animate-spin"/>
            {{ schedule.saving.value ? 'Сохраняем...' : 'Сохранить расписание' }}
          </Button>
        </div>
      </div>
    </template>

    <!-- Схема -->
    <DirectorySchemaTab
        v-else-if="activeTab === 'schema'"
        :vers-ctx="versCtx"
        :can-manage="canManage"
        :version-number="currentVersion?.version_number ?? String(versionId)"
        :directory-id="directoryId"
        @save="saveSchemaAndReload()"
    />

    <!-- Настройки версии -->
    <DirectoryVersionSourcePicker
        v-else-if="activeTab === 'settings' && currentVersion"
        :current-version="currentVersion"
        :vers-ctx="versCtx"
        :can-manage="canManage"
        @change="emit('changeSourceType', $event)"
    />
  </div>

  <!-- Sync modal -->
  <Dialog v-model:open="impCtx.syncModalOpen.value">
    <DialogContent class="sm:max-w-md">
      <DialogHeader>
        <DialogTitle class="flex items-center gap-2">
          <RefreshCw class="size-4"/>
          Синхронизация через proxy
        </DialogTitle>
        <DialogDescription>Будут применены режимы, настроенные во вкладке «Настройки».</DialogDescription>
      </DialogHeader>

      <div v-if="impCtx.syncRunning.value" class="flex flex-col items-center gap-4 py-6">
        <Loader2 class="size-10 animate-spin text-primary"/>
        <div class="text-center">
          <div class="font-medium">Синхронизация...</div>
          <div class="text-sm text-muted-foreground">Получаем данные через proxy</div>
        </div>
      </div>

      <div v-else-if="impCtx.syncResult.value" class="space-y-4 py-2">
        <div class="flex items-center gap-2 text-emerald-400">
          <CheckCircle2 class="size-5"/>
          <span class="font-medium">Запрос на синхронизацию отправлен</span>
        </div>
        <p class="text-sm text-muted-foreground">Импорт выполняется в фоне. Следите за прогрессом в «Истории».</p>
      </div>

      <DialogFooter>
        <Button variant="outline" @click="impCtx.syncModalOpen.value = false">
          {{ impCtx.syncResult.value ? 'Закрыть' : 'Отмена' }}
        </Button>
        <Button v-if="!impCtx.syncResult.value" :disabled="impCtx.syncRunning.value" class="gap-2" @click="doRunSync()">
          <Loader2 v-if="impCtx.syncRunning.value" class="size-4 animate-spin"/>
          <RefreshCw v-else class="size-4"/>
          Запустить
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
