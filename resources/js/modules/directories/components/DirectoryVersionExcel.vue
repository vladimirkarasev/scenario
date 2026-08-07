<script setup lang="ts">
/* eslint-disable vue/no-mutating-props -- composable context objects passed as props, mutations are on reactive refs inside */
import PageTabs from '@/components/PageTabs.vue'
import DirectoryItemsTable from './DirectoryItemsTable.vue'
import DirectoryImportHistory from './DirectoryImportHistory.vue'
import DirectorySchemaTab from './DirectorySchemaTab.vue'
import DirectorySettingsExcel from './DirectorySettingsExcel.vue'
import DirectoryUploadExcel from './DirectoryUploadExcel.vue'
import DirectoryVersionSourcePicker from './DirectoryVersionSourcePicker.vue'
import {History, LayoutList, Settings, Table2, Upload} from 'lucide-vue-next'
import {computed, onMounted, ref, watch} from 'vue'
import {useDirectoryImport} from '@/modules/directories/composables/useDirectoryImport'
import type {DirectoryVersion, SourceType} from '@/modules/directories/types/directory'
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

const impCtx = useDirectoryImport(props.directoryId, props.versionId)

const activeTab = ref<'items' | 'import' | 'schema' | 'settings'>('items')
const importSubTab = ref<'upload' | 'history' | 'settings'>('upload')

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
            { id: 'upload', label: 'Загрузка', icon: Upload },
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

      <DirectoryUploadExcel
          v-if="importSubTab === 'upload'"
          :directory-id="directoryId"
          :version-id="versionId"
          :schema-fields="schemaFields"
          :import-options="impCtx.importOptions"
          @import-started="importSubTab = 'history'"
      />

      <DirectoryImportHistory
          v-else-if="importSubTab === 'history'"
          ref="historyTabRef"
          :directory-id="directoryId"
          :version-id="versionId"
      />

      <div v-else-if="importSubTab === 'settings'" class="rounded-xl border border-border/60 bg-card">
        <div class="border-b border-border/60 px-6 py-4">
          <div class="text-base font-semibold">Настройки импорта</div>
          <div class="text-sm text-muted-foreground">Режим импорта из файла</div>
        </div>
        <div class="p-6">
          <DirectorySettingsExcel :options="impCtx.importOptions" :can-manage="canManage"/>
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
</template>
