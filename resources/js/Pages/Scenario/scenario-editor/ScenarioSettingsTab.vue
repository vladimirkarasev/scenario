<script setup lang="ts">
import {Badge} from '@/components/ui/badge'
import {Button} from '@/components/ui/button'
import {Card, CardContent, CardDescription, CardHeader, CardTitle} from '@/components/ui/card'
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select'
import {FormInput, FormRow, FormTagSearch, FormTextarea} from '@/components/form'
import {Check, GitBranch, Save, X} from 'lucide-vue-next'
import type {ScenarioVersion} from '@/modules/scenario/repositories/scenarioVersionRepository'
import type {ScenarioCategory} from '@/modules/scenario/types/scenario'

type ScenarioStatus = 'active' | 'draft' | 'archived'

interface StatusConfig {
  label: string
  dot: string
  text: string
  ring: string
}

defineProps<{
  form: {
    name: string
    description: string
    alias: string
    tags: string
    status: ScenarioStatus
    active_version_id: string | null
    category_ids: string[]
  }
  errors: Record<string, string | undefined>
  canEdit: boolean
  saving: boolean
  versions: ScenarioVersion[]
  activeVersion: ScenarioVersion | null
  availableCategories: Array<ScenarioCategory & { depth: number }>
  selectedGroups: Array<Record<string, unknown>>
  statusConfig: Record<ScenarioStatus, StatusConfig>
  loadGroups: (q: string) => Promise<Array<Record<string, unknown>>>
}>()

const emit = defineEmits<{
  save: []
  toggleCategory: [id: string]
  'update:selectedGroups': [v: Array<Record<string, unknown>>]
}>()

const STATUSES: ScenarioStatus[] = ['active', 'draft', 'archived']
</script>

<template>
  <div class="grid gap-6 lg:grid-cols-[1fr_280px]">
    <Card class="border-border/60">
      <CardHeader>
        <CardTitle>Основное</CardTitle>
        <CardDescription>Название, описание и псевдонимы сценария</CardDescription>
      </CardHeader>
      <CardContent class="grid gap-5">
        <FormRow>
          <FormInput
              v-model="form.name"
              label="Название"
              placeholder="Имя сценария"
              required
              :disabled="!canEdit"
              :error="errors.name"
          />
          <FormInput
              v-model="form.alias"
              label="Alias"
              placeholder="onboarding-hr"
              hint="Уникальный идентификатор для запуска сценария"
              :disabled="!canEdit"
              :error="errors.alias"
          />
        </FormRow>
        <FormTextarea
            v-model="form.description"
            label="Описание"
            :rows="3"
            placeholder="Краткое описание сценария…"
            :disabled="!canEdit"
            :error="errors.description"
        />
        <FormTextarea
            v-model="form.tags"
            label="Tags"
            :rows="2"
            placeholder="hr, onboarding"
            hint="Через запятую или перенос строки"
            :disabled="!canEdit"
            :error="errors.tags"
        />
      </CardContent>
    </Card>

    <div class="space-y-4">
      <Card class="border-border/60">
        <CardHeader class="pb-3">
          <CardTitle class="text-sm">Статус</CardTitle>
        </CardHeader>
        <CardContent class="space-y-1.5">
          <button
              v-for="s in STATUSES"
              :key="s"
              type="button"
              class="flex w-full items-center gap-3 rounded-lg border px-3 py-2.5 text-left text-sm transition"
              :class="form.status === s
                            ? 'border-primary bg-primary/5 ring-1 ring-primary/20'
                            : 'border-border hover:border-border/80 hover:bg-muted/40'"
              :disabled="!canEdit"
              @click="form.status = s"
          >
            <div
                class="flex size-4 flex-none items-center justify-center rounded-full border transition"
                :class="form.status === s ? 'border-primary bg-primary' : 'border-border bg-background'"
            >
              <Check v-if="form.status === s" class="size-2.5 text-primary-foreground"/>
            </div>
            <span class="size-2 flex-none rounded-full" :class="statusConfig[s].dot"/>
            <span
                class="font-medium"
                :class="form.status === s ? 'text-foreground' : 'text-muted-foreground'"
            >
                            {{ statusConfig[s].label }}
                        </span>
          </button>
        </CardContent>
      </Card>

      <Card class="border-border/60">
        <CardHeader class="pb-3">
          <CardTitle class="text-sm">Активная версия</CardTitle>
        </CardHeader>
        <CardContent class="space-y-2">
          <Select
              :model-value="form.active_version_id ?? '__none__'"
              :disabled="!canEdit"
              @update:model-value="(v: string) => form.active_version_id = v === '__none__' ? null : v"
          >
            <SelectTrigger>
              <SelectValue placeholder="Последняя версия"/>
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="__none__">Последняя версия</SelectItem>
              <SelectItem
                  v-for="v in versions"
                  :key="v.id"
                  :value="v.id"
              >{{ v.name }}
              </SelectItem>
            </SelectContent>
          </Select>
          <p v-if="activeVersion" class="flex items-center gap-1.5 text-xs text-muted-foreground">
            <GitBranch class="size-3"/>
            {{ activeVersion.revisions.length }} ревизий
          </p>
        </CardContent>
      </Card>

      <Card class="border-border/60">
        <CardHeader class="pb-3">
          <CardTitle class="text-sm">Папки</CardTitle>
        </CardHeader>
        <CardContent class="space-y-3">
          <div
              v-if="form.category_ids.length"
              class="flex flex-wrap gap-1.5"
          >
            <Badge
                v-for="id in form.category_ids"
                :key="id"
                variant="secondary"
                class="gap-1 pr-1"
            >
              {{ availableCategories.find(c => c.id === id)?.name ?? id }}
              <button
                  v-if="canEdit"
                  type="button"
                  class="ml-0.5 rounded leading-none hover:text-destructive"
                  @click="emit('toggleCategory', id)"
              >
                <X class="size-3"/>
              </button>
            </Badge>
          </div>
          <div class="max-h-48 overflow-y-auto rounded-md border">
            <label
                v-for="cat in availableCategories"
                :key="cat.id"
                class="flex cursor-pointer items-center gap-2.5 border-b px-3 py-2 text-sm transition last:border-0 hover:bg-muted/40"
                :style="{ paddingLeft: `${12 + (cat.depth ?? 0) * 14}px` }"
            >
              <div
                  class="flex size-4 flex-none items-center justify-center rounded border transition"
                  :class="form.category_ids.includes(cat.id)
                                    ? 'border-primary bg-primary'
                                    : 'border-border bg-background'"
                  @click="emit('toggleCategory', cat.id)"
              >
                <Check v-if="form.category_ids.includes(cat.id)" class="size-2.5 text-primary-foreground"/>
              </div>
              <span
                  class="select-none truncate"
                  :class="form.category_ids.includes(cat.id) ? 'font-medium text-foreground' : 'text-muted-foreground'"
                  @click="emit('toggleCategory', cat.id)"
              >{{ cat.name }}</span>
            </label>
            <div v-if="!availableCategories.length" class="px-3 py-6 text-center text-sm text-muted-foreground">
              Нет папок
            </div>
          </div>
        </CardContent>
      </Card>

      <Card class="border-border/60">
        <CardHeader class="pb-3">
          <CardTitle class="text-sm">Группы доступа</CardTitle>
          <CardDescription>Пользователи в этих группах увидят сценарий в Workspace. Если групп нет — сценарий виден
            только администратору.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <FormTagSearch
              :model-value="selectedGroups"
              :loader="loadGroups"
              value-key="id"
              display-key="name"
              placeholder="Поиск группы…"
              @update:model-value="emit('update:selectedGroups', $event)"
          />
        </CardContent>
      </Card>
    </div>
  </div>

  <div v-if="canEdit" class="flex justify-end">
    <Button :disabled="saving" class="gap-2" @click="emit('save')">
      <Save class="size-4"/>
      {{ saving ? 'Сохраняем…' : 'Сохранить' }}
    </Button>
  </div>
</template>
