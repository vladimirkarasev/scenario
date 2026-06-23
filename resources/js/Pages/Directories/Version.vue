<script setup lang="ts">
import AppShell from '@/layouts/AppShell.vue'
import { defineAsyncComponent, computed, onMounted } from 'vue'
import { useDashboardNavigation } from '@/composables/useDashboardNavigation'
import { useDirectoryDetail } from '@/modules/directories/composables/useDirectoryDetail'
import { useDirectoryVersions } from '@/modules/directories/composables/useDirectoryVersions'
import { useAuthStore } from '@/stores/auth'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Loader2, Star, ArrowLeft } from 'lucide-vue-next'
import { Head, Link } from '@inertiajs/vue3'
import type { SourceType } from '@/modules/directories/types/directory'

const props = defineProps<{ directoryId: string; versionId: string }>()
const DirectoryVersionManual   = defineAsyncComponent(() => import('@/modules/directories/components/DirectoryVersionManual.vue'))
const DirectoryVersionExcel    = defineAsyncComponent(() => import('@/modules/directories/components/DirectoryVersionExcel.vue'))
const DirectoryVersionApi      = defineAsyncComponent(() => import('@/modules/directories/components/DirectoryVersionApi.vue'))
const DirectoryVersionExternal = defineAsyncComponent(() => import('@/modules/directories/components/DirectoryVersionExternal.vue'))

const { navigationItems } = useDashboardNavigation()
const auth      = useAuthStore()
const canManage = computed(() => auth.hasPermission('directory_create'))
const canDelete = computed(() => auth.hasPermission('directory_delete'))

const detail  = useDirectoryDetail(props.directoryId)
const versCtx = useDirectoryVersions(props.directoryId)

const versionIdNum   = computed(() => Number(props.versionId))
const currentVersion = computed(() => versCtx.versions.value.find(v => v.id === versionIdNum.value) ?? null)
const sourceType     = computed(() => currentVersion.value?.source_type ?? 'manual')

const panelComponent = computed(() => {
  if (sourceType.value === 'excel')    return DirectoryVersionExcel
  if (sourceType.value === 'api')      return DirectoryVersionApi
  if (sourceType.value === 'external') return DirectoryVersionExternal
  return DirectoryVersionManual
})

const commonProps = computed(() => ({
  directoryId:    props.directoryId,
  versionId:      versionIdNum.value,
  currentVersion: currentVersion.value,
  detail,
  versCtx,
  canManage:      canManage.value,
  canDelete:      canDelete.value,
}))

async function changeSourceType(type: SourceType): Promise<void> {
  if (!currentVersion.value || currentVersion.value.source_type === type) return
  await versCtx.saveVersionSettings(versionIdNum.value, type)
}

onMounted(() => {
  void detail.load()
  void versCtx.loadVersions()
})
</script>

<template>
  <Head title="Версия справочника" />

  <AppShell
    :title="detail.meta.name || 'Справочник'"
    :description="detail.meta.description ?? undefined"
    :auth-user="auth.user"
    :navigation-items="navigationItems"
  >
    <div v-if="detail.loading.value && !detail.directory.value" class="flex items-center justify-center py-24 text-muted-foreground">
      <Loader2 class="size-6 animate-spin mr-3" />
      Загрузка...
    </div>

    <div v-else class="mx-auto max-w-6xl space-y-6 p-6">
<!-- Шапка -->
      <div class="flex items-start justify-between gap-4">
        <div class="space-y-2">
          <h1 class="text-2xl font-semibold tracking-tight">{{ detail.meta.name }}</h1>
          <div class="flex flex-wrap items-center gap-2">
            <Badge class="font-mono gap-1">v{{ currentVersion?.version_number ?? versionId }}</Badge>
            <Badge v-if="currentVersion?.is_active" class="gap-1">
              <Star class="size-3" />активная
            </Badge>
          </div>
        </div>
        <Button variant="outline" size="sm" as-child>
          <Link :href="route('directories.settings', directoryId)">
            <ArrowLeft class="mr-2 size-4" />К настройкам
          </Link>
        </Button>
      </div>

      <!-- Тип-специфичная панель -->
      <component
        :is="panelComponent"
        v-bind="commonProps"
        @change-source-type="changeSourceType"
      />
</div>
  </AppShell>
</template>
