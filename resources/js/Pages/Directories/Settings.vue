<script setup lang="ts">
import AppShell from '@/layouts/AppShell.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import DirectoryVersionsTable from './components/DirectoryVersionsTable.vue'
import CreateVersionDialog from './components/CreateVersionDialog.vue'
import PageTabs from '@/components/PageTabs.vue'
import {useDashboardNavigation} from '@/composables/useDashboardNavigation'
import {useDirectoryDetail} from '@/modules/directories/composables/useDirectoryDetail'
import {useDirectoryVersions} from '@/modules/directories/composables/useDirectoryVersions'
import {Badge} from '@/components/ui/badge'
import {Button} from '@/components/ui/button'
import {
  Card, CardContent, CardDescription, CardHeader, CardTitle,
} from '@/components/ui/card'
import {Input} from '@/components/ui/input'
import {Label} from '@/components/ui/label'
import {Textarea} from '@/components/ui/textarea'
import {Head, Link} from '@inertiajs/vue3'
import {useAuthStore} from '@/stores/auth'
import {
  ArrowLeft, GitBranch, Loader2, Settings, Star,
} from 'lucide-vue-next'
import {categoryRepository} from '@/modules/scenario/repositories/categoryRepository'
import type {CategoryOption} from '@/modules/scenario/repositories/categoryRepository'
import {computed, onMounted, ref} from 'vue'

const props = defineProps<{ directoryId: string }>()

const {navigationItems} = useDashboardNavigation()
const auth = useAuthStore()
const canManage = computed(() => auth.hasPermission('directory_create'))
const canDelete = computed(() => auth.hasPermission('directory_delete'))

const detail = useDirectoryDetail(props.directoryId)
const versCtx = useDirectoryVersions(props.directoryId)

const activeTab = ref<'settings' | 'versions'>('settings')
const activeVersion = computed(() => versCtx.versions.value.find(v => v.is_active) ?? null)

const categories = ref<CategoryOption[]>([])

// Плоское дерево с глубиной для отрисовки чекбоксов с отступами.
const categoryTree = computed<{ cat: CategoryOption; depth: number }[]>(() => {
  const children = (parentId: string | null) =>
      categories.value.filter(c => (c.parent_id ?? null) === parentId)
  const flatten = (parentId: string | null, depth: number): { cat: CategoryOption; depth: number }[] =>
      children(parentId).flatMap(c => [{cat: c, depth}, ...flatten(c.id, depth + 1)])
  return flatten(null, 0)
})

function toggleCategory(id: string): void {
  const idx = detail.meta.category_ids.indexOf(id)
  if (idx === -1) detail.meta.category_ids.push(id)
  else detail.meta.category_ids.splice(idx, 1)
}

onMounted(async () => {
  await detail.load()
  void versCtx.loadVersions()
  void categoryRepository.options().then(items => {
    categories.value = items
  })
})

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


</script>

<template>
  <Head title="Настройки справочника"/>

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
          <p v-if="detail.meta.description" class="text-sm text-muted-foreground">{{ detail.meta.description }}</p>
          <div class="flex flex-wrap items-center gap-2">
            <Badge v-if="activeVersion" class="gap-1">
              <Star class="size-3"/>
              v{{ activeVersion.version_number }} · активная
            </Badge>
          </div>
        </div>
        <Button variant="outline" size="sm" as-child>
          <Link :href="route('directories')">
            <ArrowLeft class="mr-2 size-4"/>
            Назад
          </Link>
        </Button>
      </div>

      <PageTabs
          v-model="activeTab"
          :tabs="[
                    { id: 'settings', label: 'Настройки', icon: Settings },
                    { id: 'versions', label: 'Версии', icon: GitBranch },
                ]"
      />

      <!-- ── TAB: НАСТРОЙКИ ───────────────────────────────────────── -->
      <template v-if="activeTab === 'settings'">
        <Card class="border-border/60">
          <CardHeader>
            <CardTitle>Основные настройки</CardTitle>
            <CardDescription>Имя, slug, описание и настройки источника. Схема колонок настраивается в версии.
            </CardDescription>
          </CardHeader>
          <CardContent class="grid gap-6">
            <div v-if="detail.saveError.value"
                 class="rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
              {{ detail.saveError.value }}
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
              <div class="space-y-2">
                <Label for="s-name">Название</Label>
                <Input id="s-name" v-model="detail.meta.name" :disabled="!canManage"/>
              </div>
              <div class="space-y-2">
                <Label for="s-slug">Slug</Label>
                <Input id="s-slug" v-model="detail.meta.slug" :disabled="!canManage"/>
              </div>
            </div>
            <div class="space-y-2">
              <Label>Категории</Label>
              <div v-if="!categories.length" class="text-sm text-muted-foreground">
                Нет доступных категорий
              </div>
              <div v-else class="max-h-60 overflow-y-auto rounded-xl border border-border/60 p-1">
                <label
                    v-for="{ cat, depth } in categoryTree"
                    :key="cat.id"
                    class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 transition hover:bg-muted/40"
                    :class="canManage ? 'cursor-pointer' : 'cursor-default'"
                >
                  <input
                      type="checkbox"
                      class="size-4 shrink-0 rounded accent-blue-600 disabled:opacity-50"
                      :style="{ marginLeft: `${depth * 18}px` }"
                      :checked="detail.meta.category_ids.includes(cat.id)"
                      :disabled="!canManage"
                      @change="toggleCategory(cat.id)"
                  />
                  <span class="truncate text-sm">{{ cat.name }}</span>
                </label>
              </div>
            </div>
            <div class="space-y-2">
              <Label for="s-desc">Описание</Label>
              <Textarea id="s-desc" v-model="detail.meta.description" :rows="2" :disabled="!canManage"/>
            </div>
            <Button v-if="canManage" :disabled="detail.saving.value" class="gap-2 self-start" @click="detail.save()">
              <Loader2 v-if="detail.saving.value" class="size-4 animate-spin"/>
              {{ detail.saving.value ? 'Сохраняем...' : 'Сохранить настройки' }}
            </Button>
          </CardContent>
        </Card>
      </template>

      <!-- ── TAB: ВЕРСИИ ──────────────────────────────────────────── -->
      <DirectoryVersionsTable
          v-else-if="activeTab === 'versions'"
          :versions="versCtx.versions.value"
          :loading="versCtx.loading.value"
          :directory-id="props.directoryId"
          :can-manage="canManage"
          :can-delete="canDelete"
          :format-date-time="fmtDateTime"
          @create-version="versCtx.createDialogOpen.value = true"
          @activate="(v) => { versCtx.activatingVersion.value = v; versCtx.activateDialogOpen.value = true }"
          @remove="(v) => { versCtx.deletingVersion.value = v; versCtx.deleteDialogOpen.value = true }"
      />
    </div>
  </AppShell>

  <!-- ── Dialogs ─────────────────────────────────────────────────────── -->

  <ConfirmDialog
      :open="versCtx.activateDialogOpen.value"
      :title="`Активировать v${versCtx.activatingVersion.value?.version_number}?`"
      :loading="versCtx.activateLoading.value"
      confirm-label="Активировать"
      variant="default"
      @update:open="(v: boolean) => versCtx.activateDialogOpen.value = v"
      @confirm="versCtx.confirmActivate(detail.flash)"
  >
    Текущая активная версия будет деактивирована.
  </ConfirmDialog>

  <ConfirmDialog
      :open="versCtx.deleteDialogOpen.value"
      :title="`Удалить версию v${versCtx.deletingVersion.value?.version_number}?`"
      :loading="versCtx.deleteLoading.value"
      :error="versCtx.deleteError.value"
      @update:open="(v: boolean) => versCtx.deleteDialogOpen.value = v"
      @confirm="versCtx.confirmDelete()"
  >
    Все записи и импорты этой версии будут удалены без возможности восстановления.
  </ConfirmDialog>

  <CreateVersionDialog :vers-ctx="versCtx" :flash="detail.flash"/>
</template>