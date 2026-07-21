<script setup lang="ts">
/* eslint-disable vue/no-mutating-props -- composable context objects passed as props, mutations are on reactive refs inside */
import PageTabs from '@/components/PageTabs.vue'
import DirectorySchemaTab from './DirectorySchemaTab.vue'
import DirectorySettingsApi from './DirectorySettingsApi.vue'
import DirectoryVersionSourcePicker from './DirectoryVersionSourcePicker.vue'
import {Globe, LayoutList, Settings} from 'lucide-vue-next'
import {computed, reactive, ref, watch} from 'vue'
import {useDirectoryProxyPicker} from '@/modules/directories/composables/useDirectoryProxyPicker'
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

const proxyPicker = useDirectoryProxyPicker(
    () => props.detail.meta.proxy_uuid,
    (uuid) => {
      props.detail.meta.proxy_uuid = uuid
    },
)

const activeTab = ref<'data' | 'schema' | 'settings' | 'source'>('data')

const syncOpts = reactive<DirectoryVersionSyncOptions>({add_new: true, update_existing: true, delete_unused: false})

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

async function saveSettings(): Promise<void> {
  await props.detail.save()
}
</script>

<template>
  <div class="space-y-4">
    <PageTabs
        v-model="activeTab"
        :tabs="[
        { id: 'data', label: 'Данные', icon: Globe },
        { id: 'schema', label: 'Схема', icon: LayoutList },
        { id: 'settings', label: 'Настройки', icon: Settings },
      ]"
    />

    <!-- Данные — плейсхолдер -->
    <template v-if="activeTab === 'data'">
      <div class="rounded-xl border border-border/60 bg-card">
        <div class="flex flex-col items-center gap-4 py-16 text-center">
          <div class="flex size-16 items-center justify-center rounded-2xl border border-primary/20 bg-primary/5">
            <Globe class="size-8 text-primary/60"/>
          </div>
          <div class="space-y-1">
            <div class="text-base font-semibold">Данные хранятся во внешнем источнике</div>
            <div class="text-sm text-muted-foreground">
              Этот справочник — виртуальный вид над прокси.<br>
              Данные не копируются в БД и читаются напрямую через маппинг полей.
            </div>
          </div>
          <div v-if="detail.meta.proxy_uuid"
               class="flex items-center gap-2 rounded-lg border border-border/60 bg-muted/20 px-4 py-2 text-sm">
            <Globe class="size-3.5 shrink-0 text-muted-foreground"/>
            <span class="font-medium">{{ proxyPicker.selected.value?.name ?? detail.meta.proxy_uuid }}</span>
            <span class="font-mono text-xs text-muted-foreground">{{ proxyPicker.selected.value?.code }}</span>
          </div>
          <p v-else class="text-sm text-amber-400">Прокси не выбран — настройте в разделе «Настройки»</p>
        </div>
      </div>
    </template>

    <!-- Схема -->
    <DirectorySchemaTab
        v-else-if="activeTab === 'schema'"
        :vers-ctx="versCtx"
        :can-manage="canManage"
        :version-number="currentVersion?.version_number ?? String(versionId)"
        @save="versCtx.saveSchema(versionId)"
    />

    <!-- Настройки: прокси + маппинг -->
    <template v-else-if="activeTab === 'settings'">
      <div class="rounded-xl border border-border/60 bg-card">
        <div class="border-b border-border/60 px-6 py-4">
          <div class="text-base font-semibold">Прокси и маппинг полей</div>
          <div class="text-sm text-muted-foreground">Выберите прокси и сопоставьте его поля со схемой справочника</div>
        </div>
        <div class="space-y-6 p-6">
          <div v-if="detail.saveError.value"
               class="rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
            {{ detail.saveError.value }}
          </div>
          <DirectorySettingsApi
              :schema-fields="schemaFields"
              :field-mapping="detail.meta.field_mapping"
              :proxy-picker="proxyPicker"
              :sync-opts="syncOpts"
              :match-by="detail.meta.match_by"
              :saving="detail.saving.value"
              :save-error="null"
              :can-manage="canManage"
              :show-sync-options="false"
              @save="saveSettings()"
          />
        </div>
      </div>

      <!-- Тип источника -->
      <DirectoryVersionSourcePicker
          v-if="currentVersion"
          :current-version="currentVersion"
          :vers-ctx="versCtx"
          :can-manage="canManage"
          @change="emit('changeSourceType', $event)"
      />
    </template>
  </div>
</template>
