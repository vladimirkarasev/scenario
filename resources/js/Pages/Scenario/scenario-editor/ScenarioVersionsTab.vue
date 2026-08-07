<script setup lang="ts">
import {Badge} from '@/components/ui/badge'
import {Button} from '@/components/ui/button'
import {Card, CardContent} from '@/components/ui/card'
import {Separator} from '@/components/ui/separator'
import {GitBranch, Play, Plus, Trash2, Workflow} from 'lucide-vue-next'
import {Link} from '@inertiajs/vue3'
import type {ScenarioVersion} from '@/modules/scenario/types/scenario-version'

defineProps<{
  versions: ScenarioVersion[]
  activeVersionId: string | null
  canEdit: boolean
  playLaunching: boolean
  formatDate: (iso: string) => string
}>()

const emit = defineEmits<{
  create: []
  play: [versionId: string]
  remove: [version: ScenarioVersion]
}>()

const VERSION_STATUS_CONFIG: Record<string, { label: string; bg: string; text: string }> = {
  active: {label: 'Активная', bg: 'bg-emerald-50', text: 'text-emerald-700'},
  draft: {label: 'Черновик', bg: 'bg-amber-50', text: 'text-amber-700'},
  archived: {label: 'Архив', bg: 'bg-slate-100', text: 'text-slate-500'},
}
</script>

<template>
  <div class="flex items-center justify-between">
    <div>
      <h2 class="text-base font-semibold text-foreground">Версии</h2>
      <p class="text-sm text-muted-foreground">Каждая версия — отдельный граф сценария</p>
    </div>
    <Button v-if="canEdit" size="sm" class="gap-2" @click="emit('create')">
      <Plus class="size-4"/>
      Новая версия
    </Button>
  </div>

  <div
      v-if="!versions.length"
      class="flex flex-col items-center rounded-xl border border-dashed py-16 text-center"
  >
    <div class="mb-3 flex size-12 items-center justify-center rounded-xl bg-muted text-muted-foreground">
      <GitBranch class="size-5"/>
    </div>
    <p class="text-sm font-semibold text-foreground">Нет версий</p>
    <p class="mt-1 text-xs text-muted-foreground">Создайте первую версию, чтобы начать строить граф</p>
  </div>

  <div class="space-y-3">
    <Card
        v-for="v in versions"
        :key="v.id"
        class="border-border/60 transition hover:shadow-sm"
    >
      <CardContent class="flex items-center gap-4 px-5 py-4">
        <div
            class="flex size-10 flex-none items-center justify-center rounded-xl transition"
            :class="v.status === 'active' ? 'bg-emerald-50 text-emerald-600' : 'bg-muted text-muted-foreground'"
        >
          <Workflow class="size-[18px]"/>
        </div>

        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-2">
            <span class="truncate text-sm font-semibold text-foreground">{{ v.name || 'Без названия' }}</span>
            <Badge v-if="v.id === activeVersionId" class="gap-1">
              <span class="size-1.5 rounded-full bg-current opacity-80"/>
              Активная
            </Badge>
            <Badge v-else variant="secondary">
              {{ VERSION_STATUS_CONFIG[v.status]?.label ?? v.status }}
            </Badge>
          </div>
          <div class="mt-0.5 flex items-center gap-3 text-xs text-muted-foreground">
                        <span class="flex items-center gap-1">
                            <GitBranch class="size-3"/>
                            {{ v.revisions.length }} ревизий
                        </span>
            <template v-if="v.updated_at">
              <span>·</span>
              <span>обновлён {{ formatDate(v.updated_at) }}</span>
            </template>
          </div>
        </div>

        <div class="flex shrink-0 items-center gap-1">
          <Button
              variant="ghost"
              size="icon"
              class="size-8 text-muted-foreground"
              title="Запустить"
              :disabled="playLaunching"
              @click="emit('play', v.id)"
          >
            <Play class="size-3.5"/>
          </Button>
          <Button variant="ghost" size="icon" class="size-8 text-muted-foreground" title="Редактировать граф" as-child>
            <Link :href="route('scenario-versions.edit', v.id)">
              <Workflow class="size-3.5"/>
            </Link>
          </Button>
          <Separator orientation="vertical" class="mx-1 h-4"/>
          <Button
              v-if="canEdit"
              variant="ghost"
              size="icon"
              class="size-8 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
              title="Удалить"
              @click="emit('remove', v)"
          >
            <Trash2 class="size-3.5"/>
          </Button>
        </div>
      </CardContent>
    </Card>
  </div>
</template>
