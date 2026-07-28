<script setup lang="ts">
/* eslint-disable vue/no-mutating-props -- composable context objects passed as props, mutations are on reactive refs inside */
import PageTabs from '@/components/PageTabs.vue'
import DirectoryItemsTable from './DirectoryItemsTable.vue'
import DirectorySchemaTab from './DirectorySchemaTab.vue'
import DirectoryVersionSourcePicker from './DirectoryVersionSourcePicker.vue'
import {LayoutList, Settings, Table2} from 'lucide-vue-next'
import {computed, ref, watch} from 'vue'
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

const activeTab = ref<'items' | 'schema' | 'settings'>('items')

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

const itemsTableRef = ref<InstanceType<typeof DirectoryItemsTable> | null>(null)

async function saveSchemaAndReload(): Promise<void> {
  await props.versCtx.saveSchema(props.versionId)
  await itemsTableRef.value?.clearFiltersAndReload()
}
</script>

<template>
  <div class="space-y-4">
    <PageTabs
        v-model="activeTab"
        :tabs="[
        { id: 'items', label: 'Данные', icon: Table2 },
        { id: 'schema', label: 'Схема', icon: LayoutList },
        { id: 'settings', label: 'Настройки', icon: Settings },
      ]"
    />

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

    <DirectorySchemaTab
        v-else-if="activeTab === 'schema'"
        :vers-ctx="versCtx"
        :can-manage="canManage"
        :version-number="currentVersion?.version_number ?? String(versionId)"
        :directory-id="directoryId"
        @save="saveSchemaAndReload()"
    />

    <DirectoryVersionSourcePicker
        v-else-if="activeTab === 'settings' && currentVersion"
        :current-version="currentVersion"
        :vers-ctx="versCtx"
        :can-manage="canManage"
        @change="emit('changeSourceType', $event)"
    />
  </div>
</template>
