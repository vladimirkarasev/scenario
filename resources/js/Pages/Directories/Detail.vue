<script setup lang="ts">
import AppShell from '@/layouts/AppShell.vue'
import DirectoryHistoryTab from '@/modules/directories/components/DirectoryHistoryTab.vue'
import PageTabs from '@/components/PageTabs.vue'
import DirectorySettingsTab from '@/modules/directories/components/DirectorySettingsTab.vue'
import {useDashboardNavigation} from '@/composables/useDashboardNavigation'
import {useDirectoryDetail} from '@/modules/directories/composables/useDirectoryDetail'
import {useDirectoryItems} from '@/modules/directories/composables/useDirectoryItems'
import {useDirectoryVersions} from '@/modules/directories/composables/useDirectoryVersions'
import {useDirectoryImport} from '@/modules/directories/composables/useDirectoryImport'
import {Badge} from '@/components/ui/badge'
import {Button} from '@/components/ui/button'
import {
  Card, CardContent, CardDescription, CardHeader, CardTitle,
} from '@/components/ui/card'
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog'
import {Input} from '@/components/ui/input'
import {Label} from '@/components/ui/label'
import {NativeSelect} from '@/components/ui/native-select'
import {Separator} from '@/components/ui/separator'
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table'
import {Textarea} from '@/components/ui/textarea'
import {Head, Link} from '@inertiajs/vue3'
import {useAuthStore} from '@/stores/auth'
import {
  ArrowLeft, Check, CheckCircle2, Copy,
  GitBranch, Globe, History, Import, Loader2,
  Pencil, Plus, RefreshCw, Search, Settings, Star,
  Table2, Trash2, Upload, XCircle, Zap,
} from 'lucide-vue-next'
import {useDirectoryProxyPicker} from '@/modules/directories/composables/useDirectoryProxyPicker'
import {toSlug} from '@/lib/slug'
import {computed, onMounted, ref, watch} from 'vue'

const props = defineProps<{ directoryId: string }>()

const {navigationItems} = useDashboardNavigation()
const auth = useAuthStore()
const canManage = computed(() => auth.hasPermission('directory_create'))
const canDelete = computed(() => auth.hasPermission('directory_delete'))

// ── Composables ───────────────────────────────────────────────────────────────

const detail = useDirectoryDetail(props.directoryId)
const itemsCtx = useDirectoryItems(props.directoryId)
const versCtx = useDirectoryVersions(props.directoryId)
const impCtx = useDirectoryImport(props.directoryId)

// ── Tabs ──────────────────────────────────────────────────────────────────────

const activeTab = ref('items')

// ── Proxy picker ──────────────────────────────────────────────────────────────

const proxyPicker = useDirectoryProxyPicker(
    () => detail.meta.proxy_uuid,
    (uuid) => {
      detail.meta.proxy_uuid = uuid
    },
)

// ── Computed ──────────────────────────────────────────────────────────────────

const activeVersion = computed(() => versCtx.versions.value.find(v => v.is_active) ?? null)
const schemaFields = computed(() => detail.meta.fields.filter(f => f.key !== ''))

// ── Load all data on mount ────────────────────────────────────────────────────

onMounted(async () => {
  await detail.load()
  const activeV = detail.directory.value?.active_version ?? detail.directory.value?.latest_version
  if (detail.directory.value?.import_settings) impCtx.restoreImportOptions(detail.directory.value.import_settings)
  if (detail.meta.proxy_uuid) void proxyPicker.loadFields(detail.meta.proxy_uuid)
  await Promise.all([
    itemsCtx.loadItems(activeV?.id),
    versCtx.loadVersions(),
    impCtx.loadImports(),
  ])
})

// ── Helpers ───────────────────────────────────────────────────────────────────

function fmtDateTime(iso: string | null): string {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('ru-RU', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const manualKeyEdited = new WeakSet<object>()

watch(() => detail.meta.fields, () => {
  for (const f of detail.meta.fields) {
    if (f.key) manualKeyEdited.add(f)
  }
}, {immediate: true})

function onFieldNameInput(field: { key: string; name: string }): void {
  if (!manualKeyEdited.has(field)) field.key = toSlug(field.name)
}

function onFieldKeyInput(field: object): void {
  manualKeyEdited.add(field)
}

function addSchemaField(): void {
  detail.meta.fields.push({
    key: '',
    name: '',
    type: 'string',
    filter_type: 'string',
    filter_multiple: false,
    nullable: true,
    filterable: false,
    searchable: false,
    filter_operator: 'contains',
    filter_placeholder: null,
    default: null,
    sort_order: 0,
    rules: [],
    options: [],
  })
}

function removeSchemaField(index: number): void {
  if (detail.meta.fields.length > 1) detail.meta.fields.splice(index, 1)
}

async function doApiSync(): Promise<void> {
  await detail.save()
  await impCtx.runSync()
  if (!impCtx.syncRunning.value) activeTab.value = 'history'
}
</script>

<template>
  <Head title="Справочник"/>

  <AppShell
      :title="detail.meta.name || 'Справочник'"
      :description="detail.meta.description ?? undefined"
      :auth-user="auth.user"
      :navigation-items="navigationItems"
  >
    <div v-if="detail.loading.value && !detail.directory.value"
         class="flex items-center justify-center py-24 text-muted-foreground">
      <Loader2 class="size-6 animate-spin mr-3"/>
      Загрузка...
    </div>

    <div v-else class="mx-auto max-w-6xl space-y-6 p-6">
      <!-- Header -->
      <div class="flex items-start justify-between gap-4">
        <div class="space-y-2">
          <h1 class="text-2xl font-semibold tracking-tight text-foreground">{{ detail.meta.name }}</h1>
          <p class="text-sm text-muted-foreground">{{ detail.meta.description }}</p>
          <div class="flex flex-wrap items-center gap-2">
            <Badge v-if="activeVersion" class="gap-1">
              <Star class="size-3"/>
              v{{ activeVersion.version_number }} · активная
            </Badge>
            <Badge variant="secondary" class="gap-1">
              <Zap class="size-3"/>
              {{ detail.meta.source_type === 'api' ? 'API' : detail.meta.source_type === 'excel' ? 'Excel' : 'Manual' }}
            </Badge>
            <Badge v-if="detail.directory.value?.sync_status === 'idle'" variant="outline"
                   class="gap-1 text-emerald-500 border-emerald-500/30">
              синхронизировано
            </Badge>
            <Badge v-else-if="detail.directory.value?.sync_status === 'syncing'" variant="outline"
                   class="gap-1 text-blue-400 border-blue-400/30">
              синхронизируется
            </Badge>
            <Badge v-else-if="detail.directory.value?.sync_status === 'error'" variant="outline"
                   class="gap-1 text-destructive border-destructive/30">
              ошибка синхронизации
            </Badge>
          </div>
        </div>
        <div class="flex shrink-0 gap-2">
          <Button
              v-if="detail.meta.source_type === 'api' && canManage"
              variant="outline"
              size="sm"
              class="gap-2"
              :disabled="!detail.meta.proxy_uuid"
              :title="!detail.meta.proxy_uuid ? 'Выберите прокси в настройках' : ''"
              @click="impCtx.openSync()"
          >
            <RefreshCw class="size-4"/>
            Синхронизировать
          </Button>
          <Button variant="outline" size="sm" as-child>
            <Link :href="route('directories')">
              <ArrowLeft class="mr-2 size-4"/>
              Назад
            </Link>
          </Button>
        </div>
      </div>

      <PageTabs
          v-model="activeTab"
          :tabs="[
                    { id: 'items', label: 'Данные', icon: Table2 },
                    { id: 'versions', label: 'Версии', icon: GitBranch },
                    { id: 'import', label: 'Импорт', icon: Upload },
                    { id: 'history', label: 'История', icon: History },
                    { id: 'settings', label: 'Настройки', icon: Settings },
                ]"
      />

      <!-- ── TAB: ДАННЫЕ ──────────────────────────────────────────── -->
      <template v-if="activeTab === 'items'">
        <Card class="border-border/60">
          <CardHeader class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-1">
              <CardTitle>Элементы справочника</CardTitle>
              <CardDescription>
                {{ itemsCtx.items.value.length }} записей ·
                {{ activeVersion ? `v${activeVersion.version_number} (активная)` : 'нет версии' }}
              </CardDescription>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <Button variant="ghost" size="sm" class="h-8 text-xs text-muted-foreground" @click="itemsCtx.expandAll()">
                Раскрыть все
              </Button>
              <Button variant="ghost" size="sm" class="h-8 text-xs text-muted-foreground"
                      @click="itemsCtx.collapseAll()">
                Свернуть
              </Button>
              <template v-if="canManage">
                <div class="h-4 w-px bg-border/60"/>
                <Button size="sm" class="gap-2" @click="itemsCtx.openAddItem(schemaFields)">
                  <Plus class="size-4"/>
                  Добавить
                </Button>
              </template>
            </div>
          </CardHeader>

          <CardContent class="space-y-3">
            <!-- Bulk toolbar -->
            <div
                v-if="itemsCtx.someSelected.value && canDelete"
                class="flex items-center gap-3 rounded-xl border border-primary/20 bg-primary/5 px-4 py-2.5"
            >
              <span class="text-sm font-medium">Выбрано: {{ itemsCtx.selectedIds.value.size }}</span>
              <div class="ml-auto flex gap-2">
                <Button variant="outline" size="sm" class="h-7 text-xs" @click="itemsCtx.selectedIds.value = new Set()">
                  Снять выделение
                </Button>
                <Button variant="destructive" size="sm" class="h-7 gap-1.5 text-xs" @click="itemsCtx.deleteSelected()">
                  <Trash2 class="size-3"/>
                  Удалить выбранные
                </Button>
              </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-border/60">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead v-if="canDelete" class="w-10 pr-0">
                      <button
                          type="button"
                          class="flex size-4 items-center justify-center rounded border transition"
                          :class="itemsCtx.allSelected.value
                                                    ? 'border-primary bg-primary text-primary-foreground'
                                                    : 'border-border/60 hover:border-primary/60'"
                          @click="itemsCtx.toggleSelectAll()"
                      >
                        <Check v-if="itemsCtx.allSelected.value" class="size-2.5"/>
                      </button>
                    </TableHead>
                    <TableHead v-for="f in schemaFields" :key="f.key">{{ f.name }}</TableHead>
                    <TableHead v-if="canManage || canDelete" class="w-20 text-right">Действия</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  <TableRow v-if="itemsCtx.loading.value">
                    <TableCell :colspan="schemaFields.length + 2"
                               class="h-24 text-center text-sm text-muted-foreground">
                      <Loader2 class="inline size-4 animate-spin mr-2"/>
                      Загрузка...
                    </TableCell>
                  </TableRow>
                  <TableRow
                      v-for="node in itemsCtx.flatTree.value"
                      v-else
                      :key="node.id"
                      :class="itemsCtx.selectedIds.value.has(node.id) ? 'bg-primary/5' : ''"
                  >
                    <TableCell v-if="canDelete" class="pr-0">
                      <button
                          type="button"
                          class="flex size-4 items-center justify-center rounded border transition"
                          :class="itemsCtx.selectedIds.value.has(node.id)
                                                    ? 'border-primary bg-primary text-primary-foreground'
                                                    : 'border-border/60 hover:border-primary/60'"
                          @click="itemsCtx.toggleSelect(node.id)"
                      >
                        <Check v-if="itemsCtx.selectedIds.value.has(node.id)" class="size-2.5"/>
                      </button>
                    </TableCell>
                    <TableCell
                        v-for="(f, fIdx) in schemaFields"
                        :key="f.key"
                    >
                      <div
                          v-if="fIdx === 0"
                          class="flex items-center gap-1"
                          :style="{ paddingLeft: `${node.depth * 16}px` }"
                      >
                        <button
                            v-if="node.hasChildren"
                            type="button"
                            class="flex size-4 shrink-0 items-center justify-center text-muted-foreground transition hover:text-foreground"
                            @click="itemsCtx.treeExpanded[node.id] = !itemsCtx.treeExpanded[node.id]"
                        >
                          <svg
                              class="size-3 transition-transform duration-150"
                              :class="node.isExpanded ? 'rotate-90' : ''"
                              viewBox="0 0 24 24" fill="none"
                              stroke="currentColor" stroke-width="2"
                          >
                            <path d="m9 18 6-6-6-6"/>
                          </svg>
                        </button>
                        <div v-else class="size-4 shrink-0"/>
                        <span>{{ itemsCtx.formatFieldValue(node, f) }}</span>
                      </div>
                      <template v-else>{{ itemsCtx.formatFieldValue(node, f) }}</template>
                    </TableCell>
                    <TableCell v-if="canManage || canDelete" class="text-right">
                      <div class="flex justify-end gap-1">
                        <Button v-if="canManage" variant="ghost" size="icon" class="size-8"
                                @click="itemsCtx.openEditItem(node, schemaFields)">
                          <Pencil class="size-3.5"/>
                        </Button>
                        <Button
                            v-if="canDelete"
                            variant="ghost"
                            size="icon"
                            class="size-8 text-muted-foreground hover:text-destructive"
                            @click="itemsCtx.removeItem(node)"
                        >
                          <Trash2 class="size-3.5"/>
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                  <TableRow v-if="!itemsCtx.loading.value && !itemsCtx.flatTree.value.length">
                    <TableCell :colspan="schemaFields.length + 2"
                               class="h-24 text-center text-sm text-muted-foreground">
                      Нет элементов.
                    </TableCell>
                  </TableRow>
                </TableBody>
              </Table>
            </div>
          </CardContent>
        </Card>
      </template>

      <!-- ── TAB: ВЕРСИИ ──────────────────────────────────────────── -->
      <template v-else-if="activeTab === 'versions'">
        <Card class="border-border/60">
          <CardHeader class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-1">
              <CardTitle>Версии справочника</CardTitle>
              <CardDescription>Каждая версия — отдельный набор данных. Активная используется по умолчанию.
              </CardDescription>
            </div>
            <Button v-if="canManage" size="sm" class="gap-2 shrink-0" @click="versCtx.createDialogOpen.value = true">
              <Plus class="size-4"/>
              Создать версию
            </Button>
          </CardHeader>
          <CardContent>
            <div class="overflow-hidden rounded-xl border border-border/60">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Версия</TableHead>
                    <TableHead>Записей</TableHead>
                    <TableHead>Импортов</TableHead>
                    <TableHead>Создана</TableHead>
                    <TableHead class="text-right">Действия</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  <TableRow v-if="versCtx.loading.value">
                    <TableCell colspan="5" class="h-24 text-center text-muted-foreground">
                      <Loader2 class="inline size-4 animate-spin mr-2"/>
                      Загрузка...
                    </TableCell>
                  </TableRow>
                  <TableRow v-for="v in versCtx.versions.value" v-else :key="v.id">
                    <TableCell>
                      <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-mono font-medium">v{{ v.version_number }}</span>
                        <Badge v-if="v.is_active" class="gap-1 text-xs">
                          <Star class="size-3"/>
                          активная
                        </Badge>
                        <!-- Code inline editor -->
                        <template v-if="versCtx.editingCodeId.value === v.id">
                          <Input
                              v-model="versCtx.editingCodeValue.value"
                              class="h-6 w-32 px-1.5 text-xs font-mono"
                              placeholder="main"
                              :disabled="versCtx.codeSaving.value"
                              @keydown.enter="versCtx.saveCode(v.id)"
                              @keydown.escape="versCtx.cancelEditCode()"
                          />
                          <Button
                              size="icon"
                              variant="ghost"
                              class="size-6 text-green-500 hover:text-green-600"
                              :disabled="versCtx.codeSaving.value"
                              @click="versCtx.saveCode(v.id)"
                          >
                            <Check class="size-3"/>
                          </Button>
                          <Button
                              size="icon"
                              variant="ghost"
                              class="size-6 text-muted-foreground"
                              :disabled="versCtx.codeSaving.value"
                              @click="versCtx.cancelEditCode()"
                          >
                            <XCircle class="size-3"/>
                          </Button>
                        </template>
                        <template v-else>
                          <code v-if="v.code"
                                class="rounded bg-muted px-1.5 py-0.5 text-xs font-mono text-muted-foreground">{{
                              v.code
                            }}</code>
                          <Button
                              v-if="canManage"
                              size="icon"
                              variant="ghost"
                              class="size-5 text-muted-foreground/50 hover:text-muted-foreground"
                              @click="versCtx.startEditCode(v)"
                          >
                            <Pencil class="size-3"/>
                          </Button>
                        </template>
                      </div>
                    </TableCell>
                    <TableCell class="text-sm">{{
                        (v as unknown as {
                          items_count?: number
                        }).items_count?.toLocaleString('ru-RU') ?? '—'
                      }}
                    </TableCell>
                    <TableCell class="text-sm">{{
                        (v as unknown as { imports_count?: number }).imports_count ?? '—'
                      }}
                    </TableCell>
                    <TableCell class="text-xs text-muted-foreground">{{ fmtDateTime(v.created_at) }}</TableCell>
                    <TableCell class="text-right">
                      <div class="flex items-center justify-end gap-2">
                        <Button
                            v-if="!v.is_active && canManage"
                            variant="outline"
                            size="sm"
                            class="gap-1.5"
                            @click="versCtx.activatingVersion.value = v; versCtx.activateDialogOpen.value = true"
                        >
                          <Star class="size-3.5"/>
                          Активировать
                        </Button>
                        <Button
                            v-if="!v.is_active && canDelete"
                            variant="outline"
                            size="icon"
                            class="size-8 text-muted-foreground hover:border-destructive hover:text-destructive"
                            @click="versCtx.deletingVersion.value = v; versCtx.deleteDialogOpen.value = true"
                        >
                          <Trash2 class="size-3.5"/>
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                </TableBody>
              </Table>
            </div>
          </CardContent>
        </Card>
      </template>

      <!-- ── TAB: ИМПОРТ ──────────────────────────────────────────── -->
      <template v-else-if="activeTab === 'import' && canManage">
        <!-- API source -->
        <template v-if="detail.meta.source_type === 'api'">
          <Card class="border-border/60">
            <CardHeader>
              <CardTitle>Синхронизация из API</CardTitle>
              <CardDescription>Настройте режим импорта и сопоставьте поля прокси с полями версии справочника.
              </CardDescription>
            </CardHeader>
            <CardContent class="grid gap-6">
              <!-- Sync options -->
              <div class="grid grid-cols-3 gap-3">
                <label
                    class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition"
                    :class="impCtx.syncOptions.add_new ? 'border-primary bg-primary/5' : 'border-border/60 hover:border-primary/30'"
                    @click="impCtx.syncOptions.add_new = !impCtx.syncOptions.add_new"
                >
                  <div class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded border transition"
                       :class="impCtx.syncOptions.add_new ? 'border-primary bg-primary text-primary-foreground' : 'border-border/60'">
                    <Check v-if="impCtx.syncOptions.add_new" class="size-2.5"/>
                  </div>
                  <div>
                    <div class="font-medium">Добавить новые</div>
                    <div class="text-xs text-muted-foreground">Строки из источника, которых ещё нет в справочнике</div>
                  </div>
                </label>
                <label
                    class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition"
                    :class="impCtx.syncOptions.update_existing ? 'border-primary bg-primary/5' : 'border-border/60 hover:border-primary/30'"
                    @click="impCtx.syncOptions.update_existing = !impCtx.syncOptions.update_existing"
                >
                  <div class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded border transition"
                       :class="impCtx.syncOptions.update_existing ? 'border-primary bg-primary text-primary-foreground' : 'border-border/60'">
                    <Check v-if="impCtx.syncOptions.update_existing" class="size-2.5"/>
                  </div>
                  <div>
                    <div class="font-medium">Обновить текущие</div>
                    <div class="text-xs text-muted-foreground">Перезаписать поля у существующих записей по ключу</div>
                  </div>
                </label>
                <label
                    class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition"
                    :class="impCtx.syncOptions.delete_unused ? 'border-destructive/40 bg-destructive/5' : 'border-border/60 hover:border-primary/30'"
                    @click="impCtx.syncOptions.delete_unused = !impCtx.syncOptions.delete_unused"
                >
                  <div class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded border transition"
                       :class="impCtx.syncOptions.delete_unused ? 'border-destructive bg-destructive text-destructive-foreground' : 'border-border/60'">
                    <Check v-if="impCtx.syncOptions.delete_unused" class="size-2.5"/>
                  </div>
                  <div>
                    <div class="font-medium">Удалить неиспользованные</div>
                    <div class="text-xs text-muted-foreground">Удалить записи, которых нет в источнике</div>
                  </div>
                </label>
              </div>

              <p v-if="!detail.meta.proxy_uuid" class="text-sm text-amber-400">
                Сначала выберите прокси в настройках справочника
              </p>

              <!-- Result -->
              <div v-if="impCtx.syncResult.value"
                   class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-400">
                Запрос на синхронизацию отправлен. Следите за прогрессом в разделе «История».
              </div>

              <!-- Actions -->
              <div class="flex gap-3">
                <Button
                    :disabled="impCtx.syncRunning.value || !detail.meta.proxy_uuid || detail.saving.value"
                    class="gap-2"
                    @click="doApiSync()"
                >
                  <Loader2 v-if="impCtx.syncRunning.value || detail.saving.value" class="size-4 animate-spin"/>
                  <RefreshCw v-else class="size-4"/>
                  {{ impCtx.syncRunning.value ? 'Синхронизируется...' : 'Синхронизировать' }}
                </Button>
                <Button variant="outline" @click="activeTab = 'settings'">
                  <Settings class="mr-2 size-4"/>
                  Настройки API
                </Button>
              </div>
            </CardContent>
          </Card>
        </template>

        <!-- File import wizard -->
        <template v-else>
          <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-400">
            Данные будут импортированы в активную версию:
            <strong>v{{ activeVersion?.version_number }}</strong>
          </div>

          <!-- Step 1 -->
          <Card v-if="impCtx.importStep.value === 1" class="border-border/60">
            <CardHeader>
              <CardTitle>Импорт данных</CardTitle>
              <CardDescription>Загрузите файл и выберите режим. Колонки будут прочитаны автоматически.</CardDescription>
            </CardHeader>
            <CardContent class="grid gap-6">
              <div class="grid grid-cols-3 gap-3">
                <label
                    class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition"
                    :class="impCtx.importOptions.addNew ? 'border-primary bg-primary/5' : 'border-border/60 hover:border-primary/30'"
                    @click="impCtx.importOptions.addNew = !impCtx.importOptions.addNew"
                >
                  <div class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded border transition"
                       :class="impCtx.importOptions.addNew ? 'border-primary bg-primary text-primary-foreground' : 'border-border/60'">
                    <Check v-if="impCtx.importOptions.addNew" class="size-2.5"/>
                  </div>
                  <div>
                    <div class="font-medium">Добавить новые</div>
                    <div class="text-xs text-muted-foreground">Строки из файла, которых ещё нет в справочнике</div>
                  </div>
                </label>
                <label
                    class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition"
                    :class="impCtx.importOptions.updateExisting ? 'border-primary bg-primary/5' : 'border-border/60 hover:border-primary/30'"
                    @click="impCtx.importOptions.updateExisting = !impCtx.importOptions.updateExisting"
                >
                  <div class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded border transition"
                       :class="impCtx.importOptions.updateExisting ? 'border-primary bg-primary text-primary-foreground' : 'border-border/60'">
                    <Check v-if="impCtx.importOptions.updateExisting" class="size-2.5"/>
                  </div>
                  <div>
                    <div class="font-medium">Обновить текущие</div>
                    <div class="text-xs text-muted-foreground">Перезаписать поля у существующих записей по ключевому
                      полю
                    </div>
                  </div>
                </label>
                <label
                    class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition"
                    :class="impCtx.importOptions.deleteUnused ? 'border-destructive/40 bg-destructive/5' : 'border-border/60 hover:border-primary/30'"
                    @click="impCtx.importOptions.deleteUnused = !impCtx.importOptions.deleteUnused"
                >
                  <div class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded border transition"
                       :class="impCtx.importOptions.deleteUnused ? 'border-destructive bg-destructive text-destructive-foreground' : 'border-border/60'">
                    <Check v-if="impCtx.importOptions.deleteUnused" class="size-2.5"/>
                  </div>
                  <div>
                    <div class="font-medium">Удалить неиспользованные</div>
                    <div class="text-xs text-muted-foreground">Удалить записи, которых нет в файле</div>
                  </div>
                </label>
              </div>

              <div class="space-y-2">
                <Label>Файл (.xlsx, .csv, .ods)</Label>
                <Input type="file" accept=".xlsx,.csv,.ods,.xls" @change="impCtx.onFileSelect"/>
              </div>

              <div v-if="impCtx.importError.value"
                   class="rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                {{ impCtx.importError.value }}
              </div>

              <Button class="gap-2 self-start" @click="impCtx.goToMapping(schemaFields)">
                <Upload class="size-4"/>
                Читать колонки
              </Button>
            </CardContent>
          </Card>

          <!-- Step 2: mapping -->
          <Card v-else-if="impCtx.importStep.value === 2" class="border-border/60">
            <CardHeader class="flex flex-row items-start justify-between gap-4">
              <div class="space-y-1">
                <CardTitle>Сопоставление колонок</CardTitle>
                <CardDescription>Обнаружено {{ impCtx.parsedHeaders.value.length }} колонок в файле.</CardDescription>
              </div>
              <Button variant="outline" size="sm" class="shrink-0 gap-2" @click="impCtx.importStep.value = 1">
                <ArrowLeft class="size-4"/>
                Назад
              </Button>
            </CardHeader>
            <CardContent class="grid gap-6">
              <div class="overflow-hidden rounded-xl border border-border/60">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Поле справочника</TableHead>
                      <TableHead>Колонка в источнике</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    <TableRow v-for="f in schemaFields" :key="f.key">
                      <TableCell>
                        <div class="font-medium">{{ f.name }}</div>
                        <div class="font-mono text-xs text-muted-foreground">{{ f.key }}</div>
                      </TableCell>
                      <TableCell>
                        <select v-model="impCtx.importMapping[f.key]"
                                class="flex h-9 w-full rounded-lg border border-input bg-background px-3 text-sm">
                          <option value="">— Не сопоставлять —</option>
                          <option v-for="h in impCtx.parsedHeaders.value" :key="h.key" :value="h.key">{{
                              h.label
                            }}
                          </option>
                        </select>
                      </TableCell>
                    </TableRow>
                  </TableBody>
                </Table>
              </div>
              <div class="flex gap-3">
                <Button :disabled="impCtx.importLoading.value" class="gap-2"
                        @click="impCtx.submitImport().then(ok => { if (ok) activeTab = 'history' })">
                  <Loader2 v-if="impCtx.importLoading.value" class="size-4 animate-spin"/>
                  <Import v-else class="size-4"/>
                  {{ impCtx.importLoading.value ? 'Запускаем...' : 'Запустить импорт' }}
                </Button>
                <Button variant="outline" @click="impCtx.resetImport()">Отмена</Button>
              </div>
            </CardContent>
          </Card>
        </template>
      </template>

      <!-- Import tab: access denied -->
      <template v-else-if="activeTab === 'import' && !canManage">
        <Card class="border-border/60">
          <CardContent class="flex flex-col items-center gap-3 py-12 text-center text-muted-foreground">
            <Import class="size-10 opacity-30"/>
            <div class="text-sm">Недостаточно прав для импорта данных.</div>
          </CardContent>
        </Card>
      </template>

      <!-- ── TAB: ИСТОРИЯ ─────────────────────────────────────────── -->
      <DirectoryHistoryTab
          v-else-if="activeTab === 'history'"
          :imp-ctx="impCtx"
          :format-date-time="fmtDateTime"
      />

      <!-- ── TAB: НАСТРОЙКИ ───────────────────────────────────────── -->
      <DirectorySettingsTab
          v-else-if="activeTab === 'settings'"
          :detail="detail"
          :proxy-picker="proxyPicker"
          :schema-fields="schemaFields"
          :can-manage="canManage"
          @add-field="addSchemaField"
          @remove-field="removeSchemaField"
          @field-name-input="onFieldNameInput"
          @field-key-input="onFieldKeyInput"
          @save="detail.save()"
      />
    </div>
  </AppShell>

  <!-- ── Dialogs ─────────────────────────────────────────────────────── -->

  <!-- Edit / Add item -->
  <Dialog v-model:open="itemsCtx.editDialogOpen.value">
    <DialogContent class="sm:max-w-lg">
      <DialogHeader>
        <DialogTitle>{{ itemsCtx.editingItemId.value ? 'Редактировать запись' : 'Новая запись' }}</DialogTitle>
        <DialogDescription>Заполните поля согласно схеме справочника.</DialogDescription>
      </DialogHeader>
      <div v-if="itemsCtx.editError.value"
           class="rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
        {{ itemsCtx.editError.value }}
      </div>
      <div class="grid gap-4 py-2">
        <div v-for="f in schemaFields" :key="f.key" class="space-y-2">
          <Label :for="`ef-${f.key}`">{{ f.name }}</Label>
          <NativeSelect v-if="f.type === 'boolean'" :id="`ef-${f.key}`" v-model="itemsCtx.editFields[f.key]">
            <option value="">— Не выбрано —</option>
            <option value="true">Да</option>
            <option value="false">Нет</option>
          </NativeSelect>
          <Input v-else-if="f.type === 'date'" :id="`ef-${f.key}`" v-model="itemsCtx.editFields[f.key]" type="date"/>
          <Input v-else-if="f.type === 'datetime'" :id="`ef-${f.key}`" v-model="itemsCtx.editFields[f.key]"
                 type="datetime-local"/>
          <Input v-else-if="f.type === 'integer'" :id="`ef-${f.key}`" v-model="itemsCtx.editFields[f.key]" type="number"
                 step="1" :placeholder="`Значение ${f.name}`"/>
          <Input v-else :id="`ef-${f.key}`" v-model="itemsCtx.editFields[f.key]" :placeholder="`Значение ${f.name}`"/>
        </div>
        <div class="space-y-2">
          <Label>Родительский элемент</Label>
          <select v-model="itemsCtx.editingParentId.value"
                  class="flex h-10 w-full rounded-lg border border-input bg-background px-3 text-sm">
            <option :value="null">— Нет родителя (корневой) —</option>
            <option
                v-for="item in itemsCtx.items.value.filter(i => i.id !== itemsCtx.editingItemId.value)"
                :key="item.id"
                :value="item.id"
            >
              #{{ item.id }} · {{ Object.values(item.data ?? {})[0] ?? `#${item.id}` }}
            </option>
          </select>
        </div>
      </div>
      <DialogFooter>
        <Button variant="outline" @click="itemsCtx.editDialogOpen.value = false">Отмена</Button>
        <Button :disabled="itemsCtx.editSaving.value" @click="itemsCtx.saveItem(schemaFields)">
          <Loader2 v-if="itemsCtx.editSaving.value" class="mr-2 size-4 animate-spin"/>
          {{ itemsCtx.editingItemId.value ? 'Сохранить' : 'Добавить' }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>

  <!-- Activate version -->
  <Dialog v-model:open="versCtx.activateDialogOpen.value">
    <DialogContent class="sm:max-w-md">
      <DialogHeader>
        <DialogTitle>Активировать v{{ versCtx.activatingVersion.value?.version_number }}?</DialogTitle>
        <DialogDescription>Текущая активная версия будет деактивирована.</DialogDescription>
      </DialogHeader>
      <DialogFooter>
        <Button variant="outline" @click="versCtx.activateDialogOpen.value = false">Отмена</Button>
        <Button :disabled="versCtx.activateLoading.value" @click="versCtx.confirmActivate(detail.flash)">
          <Loader2 v-if="versCtx.activateLoading.value" class="mr-2 size-4 animate-spin"/>
          Активировать
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>

  <!-- Delete version -->
  <Dialog v-model:open="versCtx.deleteDialogOpen.value">
    <DialogContent class="sm:max-w-md">
      <DialogHeader>
        <DialogTitle>Удалить версию v{{ versCtx.deletingVersion.value?.version_number }}?</DialogTitle>
        <DialogDescription>Все записи и импорты этой версии будут удалены без возможности восстановления.
        </DialogDescription>
      </DialogHeader>
      <div v-if="versCtx.deleteError.value"
           class="rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
        {{ versCtx.deleteError.value }}
      </div>
      <DialogFooter>
        <Button variant="outline" @click="versCtx.deleteDialogOpen.value = false">Отмена</Button>
        <Button variant="destructive" :disabled="versCtx.deleteLoading.value" @click="versCtx.confirmDelete()">
          <Loader2 v-if="versCtx.deleteLoading.value" class="mr-2 size-4 animate-spin"/>
          Удалить
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>

  <!-- Create version -->
  <Dialog v-model:open="versCtx.createDialogOpen.value">
    <DialogContent class="sm:max-w-md">
      <DialogHeader>
        <DialogTitle>Создать новую версию</DialogTitle>
        <DialogDescription>Выберите способ создания версии.</DialogDescription>
      </DialogHeader>
      <div class="grid gap-3 py-2">
        <label
            class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition"
            :class="!versCtx.createClone.value ? 'border-primary bg-primary/5' : 'border-border/60 hover:border-primary/40'"
            @click="versCtx.createClone.value = false"
        >
          <input v-model="versCtx.createClone.value" type="radio" :value="false" class="mt-0.5 size-4"/>
          <div>
            <div class="font-medium">Пустая версия</div>
            <div class="text-xs text-muted-foreground">Создаётся без данных. Схема полей копируется из активной.</div>
          </div>
        </label>
        <label
            class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition"
            :class="versCtx.createClone.value ? 'border-primary bg-primary/5' : 'border-border/60 hover:border-primary/40'"
            @click="versCtx.createClone.value = true"
        >
          <input v-model="versCtx.createClone.value" type="radio" :value="true" class="mt-0.5 size-4"/>
          <div>
            <div class="flex items-center gap-2 font-medium">
              <Copy class="size-3.5"/>
              Клон активной версии
            </div>
            <div class="text-xs text-muted-foreground">Копирует все элементы и иерархию из текущей активной версии.
            </div>
          </div>
        </label>
      </div>
      <div v-if="versCtx.createError.value"
           class="rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
        {{ versCtx.createError.value }}
      </div>
      <DialogFooter>
        <Button variant="outline" @click="versCtx.createDialogOpen.value = false">Отмена</Button>
        <Button :disabled="versCtx.createLoading.value" @click="versCtx.createVersion(detail.flash)">
          <Loader2 v-if="versCtx.createLoading.value" class="mr-2 size-4 animate-spin"/>
          Создать
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>

  <!-- Proxy picker -->
  <Dialog v-model:open="proxyPicker.open.value">
    <DialogContent class="flex max-h-[80vh] flex-col gap-0 p-0 sm:max-w-lg">
      <DialogHeader class="shrink-0 border-b border-border/60 p-4 pb-3">
        <DialogTitle>Выбор прокси</DialogTitle>
        <DialogDescription>Выберите webhook-прокси для синхронизации данных</DialogDescription>
      </DialogHeader>
      <div class="shrink-0 border-b border-border/60 px-4 py-3">
        <div class="relative">
          <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"/>
          <Input v-model="proxyPicker.search.value" placeholder="Поиск по названию, коду..." class="pl-9"/>
        </div>
      </div>
      <div class="min-h-0 flex-1 overflow-y-auto p-2">
        <div v-if="proxyPicker.loading.value" class="flex items-center justify-center py-10 text-muted-foreground">
          <Loader2 class="size-4 animate-spin mr-2"/>
          Загрузка...
        </div>
        <div v-else-if="!proxyPicker.filtered.value.length" class="py-10 text-center text-sm text-muted-foreground">
          Ничего не найдено
        </div>
        <button
            v-for="proxy in proxyPicker.filtered.value"
            v-else
            :key="proxy.id"
            type="button"
            class="flex w-full items-start gap-3 rounded-xl px-3 py-3 text-left transition hover:bg-muted/40"
            :class="detail.meta.proxy_uuid === proxy.uuid ? 'bg-primary/5 ring-1 ring-primary/30' : ''"
            @click="proxyPicker.selectProxy(proxy)"
        >
          <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted/40 text-muted-foreground">
            <Globe class="size-4"/>
          </div>
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
              <span class="truncate font-medium text-foreground">{{ proxy.name }}</span>
              <Badge v-if="!proxy.is_active" variant="secondary" class="shrink-0 text-xs">неактивен</Badge>
              <Check v-if="detail.meta.proxy_uuid === proxy.uuid" class="ml-auto size-4 shrink-0 text-primary"/>
            </div>
            <div class="font-mono text-xs text-muted-foreground">{{ proxy.code }}</div>
            <div class="mt-0.5 line-clamp-1 text-xs text-muted-foreground/70">{{ proxy.description }}</div>
          </div>
        </button>
      </div>
      <div class="shrink-0 border-t border-border/60 px-4 py-3">
        <Button variant="outline" size="sm" class="w-full" @click="proxyPicker.open.value = false">Отмена</Button>
      </div>
    </DialogContent>
  </Dialog>

  <!-- Sync modal -->
  <Dialog v-model:open="impCtx.syncModalOpen.value">
    <DialogContent class="sm:max-w-md">
      <DialogHeader>
        <DialogTitle class="flex items-center gap-2">
          <RefreshCw class="size-4"/>
          Синхронизация с Remote API
        </DialogTitle>
        <DialogDescription>Выберите операции, которые будут выполнены при синхронизации.</DialogDescription>
      </DialogHeader>

      <div v-if="proxyPicker.selected.value && !impCtx.syncRunning.value && !impCtx.syncResult.value"
           class="flex items-center gap-2 rounded-lg border border-border/60 bg-muted/20 px-3 py-2 text-xs">
        <Globe class="size-3.5 shrink-0 text-muted-foreground"/>
        <span class="font-medium text-foreground">{{ proxyPicker.selected.value.name }}</span>
        <span class="ml-auto font-mono text-muted-foreground">{{ proxyPicker.selected.value.code }}</span>
      </div>

      <div v-if="!impCtx.syncRunning.value && !impCtx.syncResult.value" class="grid gap-3 py-2">
        <label
            class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition"
            :class="impCtx.syncOptions.add_new ? 'border-primary bg-primary/5' : 'border-border/60 hover:border-primary/30'"
            @click="impCtx.syncOptions.add_new = !impCtx.syncOptions.add_new"
        >
          <div class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded border transition"
               :class="impCtx.syncOptions.add_new ? 'border-primary bg-primary text-primary-foreground' : 'border-border/60'">
            <Check v-if="impCtx.syncOptions.add_new" class="size-2.5"/>
          </div>
          <div>
            <div class="font-medium">Добавить новые</div>
            <div class="text-xs text-muted-foreground">Записи из источника, которых ещё нет в справочнике</div>
          </div>
        </label>
        <label
            class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition"
            :class="impCtx.syncOptions.update_existing ? 'border-primary bg-primary/5' : 'border-border/60 hover:border-primary/30'"
            @click="impCtx.syncOptions.update_existing = !impCtx.syncOptions.update_existing"
        >
          <div class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded border transition"
               :class="impCtx.syncOptions.update_existing ? 'border-primary bg-primary text-primary-foreground' : 'border-border/60'">
            <Check v-if="impCtx.syncOptions.update_existing" class="size-2.5"/>
          </div>
          <div>
            <div class="font-medium">Обновить текущие</div>
            <div class="text-xs text-muted-foreground">Перезаписать поля у существующих записей по ключу</div>
          </div>
        </label>
        <label
            class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition"
            :class="impCtx.syncOptions.delete_unused ? 'border-destructive/40 bg-destructive/5' : 'border-border/60 hover:border-primary/30'"
            @click="impCtx.syncOptions.delete_unused = !impCtx.syncOptions.delete_unused"
        >
          <div class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded border transition"
               :class="impCtx.syncOptions.delete_unused ? 'border-destructive bg-destructive text-destructive-foreground' : 'border-border/60'">
            <Check v-if="impCtx.syncOptions.delete_unused" class="size-2.5"/>
          </div>
          <div>
            <div class="font-medium">Удалить неиспользованные</div>
            <div class="text-xs text-muted-foreground">Удалить записи, которых больше нет в источнике</div>
          </div>
        </label>
      </div>

      <div v-else-if="impCtx.syncRunning.value" class="flex flex-col items-center gap-4 py-6">
        <Loader2 class="size-10 animate-spin text-primary"/>
        <div class="text-center">
          <div class="font-medium">Синхронизация...</div>
          <div class="text-sm text-muted-foreground">Подключаемся к Remote API</div>
        </div>
      </div>

      <div v-else-if="impCtx.syncResult.value" class="space-y-4 py-2">
        <div class="flex items-center gap-2 text-emerald-400">
          <CheckCircle2 class="size-5"/>
          <span class="font-medium">Запрос на синхронизацию отправлен</span>
        </div>
        <p class="text-sm text-muted-foreground">Импорт выполняется в фоне. Следите за прогрессом в разделе
          «История».</p>
      </div>

      <DialogFooter>
        <Button variant="outline" @click="impCtx.syncModalOpen.value = false">
          {{ impCtx.syncResult.value ? 'Закрыть' : 'Отмена' }}
        </Button>
        <Button
            v-if="!impCtx.syncResult.value"
            :disabled="impCtx.syncRunning.value || (!impCtx.syncOptions.add_new && !impCtx.syncOptions.update_existing && !impCtx.syncOptions.delete_unused)"
            class="gap-2"
            @click="impCtx.runSync()"
        >
          <Loader2 v-if="impCtx.syncRunning.value" class="size-4 animate-spin"/>
          <RefreshCw v-else class="size-4"/>
          Запустить
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
