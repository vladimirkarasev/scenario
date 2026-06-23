<script setup lang="ts">
import {
  Card, CardContent, CardDescription, CardHeader, CardTitle,
} from '@/components/ui/card'
import {Button} from '@/components/ui/button'
import {Input} from '@/components/ui/input'
import {Label} from '@/components/ui/label'
import {Textarea} from '@/components/ui/textarea'
import {Separator} from '@/components/ui/separator'
import {NativeSelect} from '@/components/ui/native-select'
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table'
import {Globe, Loader2, Plus, Trash2} from 'lucide-vue-next'
import type {DirectorySchemaField, SourceType} from '@/modules/directories/types/directory'
import type {useDirectoryDetail} from '@/modules/directories/composables/useDirectoryDetail'
import {SOURCE_TYPES} from '@/modules/directories/sourceTypes'
import type {useDirectoryProxyPicker} from '@/modules/directories/composables/useDirectoryProxyPicker'

const props = defineProps<{
  detail: ReturnType<typeof useDirectoryDetail>
  proxyPicker: ReturnType<typeof useDirectoryProxyPicker>
  schemaFields: DirectorySchemaField[]
  canManage: boolean
}>()

const emit = defineEmits<{
  addField: []
  removeField: [index: number]
  fieldNameInput: [field: { key: string; name: string }]
  fieldKeyInput: [field: object]
  save: []
}>()

function setSourceType(id: SourceType): void {
  if (props.canManage) props.detail.meta.source_type = id
}
</script>

<template>
  <Card class="border-border/60">
    <CardHeader>
      <CardTitle>Основные настройки</CardTitle>
      <CardDescription>Имя, slug, описание и схема колонок справочника.</CardDescription>
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
        <Label for="s-desc">Описание</Label>
        <Textarea id="s-desc" v-model="detail.meta.description" :rows="2" :disabled="!canManage"/>
      </div>
      <div class="space-y-2">
        <Label>Тип источника</Label>
        <div class="flex gap-2">
          <button
              v-for="src in SOURCE_TYPES"
              :key="src.id"
              type="button"
              class="rounded-lg border px-4 py-2 text-sm font-medium transition"
              :class="detail.meta.source_type === src.id
                            ? 'border-primary bg-primary/5 text-foreground'
                            : 'border-border/60 text-muted-foreground hover:border-primary/40'"
              :disabled="!canManage"
              @click="setSourceType(src.id)"
          >
            {{ src.label }}
          </button>
        </div>
      </div>
      <div class="space-y-2">
        <Label>Ключевое поле</Label>
        <select v-model="detail.meta.match_by"
                class="flex h-10 w-full rounded-lg border border-input bg-background px-3 text-sm">
          <option value="">Не задано</option>
          <option v-for="f in detail.meta.fields.filter(f => f.key)" :key="f.key" :value="f.key">
            {{ f.name || f.key }} ({{ f.key }})
          </option>
        </select>
      </div>

      <template v-if="detail.meta.source_type === 'api' || detail.meta.source_type === 'external'">
        <Separator/>
        <div class="space-y-3">
          <div>
            <div class="text-sm font-semibold">Прокси</div>
            <div class="text-xs text-muted-foreground">Источник данных для синхронизации через webhook-прокси</div>
          </div>

          <div v-if="proxyPicker.selected.value"
               class="flex items-start gap-3 rounded-xl border border-primary/30 bg-primary/5 p-4">
            <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10">
              <Globe class="size-4 text-primary"/>
            </div>
            <div class="min-w-0 flex-1">
              <div class="truncate font-medium">{{ proxyPicker.selected.value.name }}</div>
              <div class="font-mono text-xs text-muted-foreground">{{ proxyPicker.selected.value.code }}</div>
              <div class="mt-0.5 text-xs text-muted-foreground line-clamp-1">{{
                  proxyPicker.selected.value.description
                }}
              </div>
            </div>
            <Button v-if="canManage" variant="ghost" size="sm" class="shrink-0 text-xs"
                    @click="proxyPicker.openPicker()">Изменить
            </Button>
          </div>

          <button
              v-else-if="canManage"
              type="button"
              class="flex w-full items-center gap-3 rounded-xl border border-dashed border-border/60 p-4 text-left transition hover:border-primary/40 hover:bg-muted/20"
              @click="proxyPicker.openPicker()"
          >
            <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted/40">
              <Plus class="size-4 text-muted-foreground"/>
            </div>
            <div>
              <div class="text-sm font-medium text-muted-foreground">Выбрать прокси</div>
              <div class="text-xs text-muted-foreground/60">Без прокси синхронизация недоступна</div>
            </div>
          </button>
        </div>

        <template v-if="detail.meta.proxy_uuid">
          <Separator/>
          <div class="space-y-3">
            <div>
              <div class="text-sm font-semibold">Сопоставление полей</div>
              <div class="text-xs text-muted-foreground">Соотнесите поля справочника с полями, которые возвращает
                прокси
              </div>
            </div>
            <div v-if="!proxyPicker.fields.value.length"
                 class="rounded-xl border border-border/60 px-4 py-3 text-sm text-muted-foreground">
              <Loader2 v-if="proxyPicker.loading.value" class="inline size-3.5 animate-spin mr-1.5"/>
              {{ proxyPicker.loading.value ? 'Загрузка полей прокси...' : 'Поля прокси недоступны' }}
            </div>
            <div v-else class="overflow-hidden rounded-xl border border-border/60">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Поле справочника</TableHead>
                    <TableHead>Поле прокси</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  <TableRow v-for="f in schemaFields" :key="f.key">
                    <TableCell>
                      <div class="font-medium">{{ f.name }}</div>
                      <div class="font-mono text-xs text-muted-foreground">{{ f.key }}</div>
                    </TableCell>
                    <TableCell>
                      <select
                          v-model="detail.meta.field_mapping[f.key]"
                          class="flex h-9 w-full rounded-lg border border-input bg-background px-3 text-sm"
                      >
                        <option value="">— Не сопоставлять —</option>
                        <option v-for="pf in proxyPicker.fields.value" :key="pf.key" :value="pf.key">
                          {{ pf.label }}
                          <template v-if="pf.example"> · пример: {{ pf.example }}</template>
                        </option>
                      </select>
                    </TableCell>
                  </TableRow>
                </TableBody>
              </Table>
            </div>
          </div>
        </template>
      </template>

      <Separator/>

      <div class="space-y-3">
        <div class="flex items-center justify-between">
          <Label>Схема колонок</Label>
          <Button v-if="canManage" type="button" variant="outline" size="sm" class="gap-2" @click="emit('addField')">
            <Plus class="size-4"/>
            Добавить
          </Button>
        </div>
        <div class="overflow-hidden rounded-xl border border-border/60">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Key</TableHead>
                <TableHead>Название</TableHead>
                <TableHead>Тип поля</TableHead>
                <TableHead class="text-center">Обязательное</TableHead>
                <TableHead class="w-12"/>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow v-for="(field, idx) in detail.meta.fields" :key="idx">
                <TableCell>
                  <Input v-model="field.key" placeholder="code" class="h-8 font-mono" :disabled="!canManage"
                         @input="emit('fieldKeyInput', field)"/>
                </TableCell>
                <TableCell>
                  <Input
                      v-model="field.name"
                      placeholder="Название"
                      class="h-8"
                      :disabled="!canManage"
                      @input="emit('fieldNameInput', field)"
                  />
                </TableCell>
                <TableCell>
                  <NativeSelect v-model="field.type" :disabled="!canManage">
                    <option value="string">Текст</option>
                    <option value="integer">Число</option>
                    <option value="boolean">Булево</option>
                    <option value="date">Дата</option>
                    <option value="datetime">Дата и время</option>
                  </NativeSelect>
                </TableCell>
                <TableCell class="text-center">
                  <input v-model="field.nullable" type="checkbox" class="size-4 rounded border-border accent-primary"
                         :disabled="!canManage"/>
                </TableCell>
                <TableCell>
                  <Button
                      v-if="canManage"
                      type="button"
                      variant="ghost"
                      size="icon"
                      class="size-8 text-muted-foreground hover:text-destructive"
                      :disabled="detail.meta.fields.length === 1"
                      @click="emit('removeField', idx)"
                  >
                    <Trash2 class="size-3.5"/>
                  </Button>
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </div>
      </div>

      <Button v-if="canManage" :disabled="detail.saving.value" class="gap-2 self-start" @click="emit('save')">
        <Loader2 v-if="detail.saving.value" class="size-4 animate-spin"/>
        {{ detail.saving.value ? 'Сохраняем...' : 'Сохранить настройки' }}
      </Button>
    </CardContent>
  </Card>
</template>
