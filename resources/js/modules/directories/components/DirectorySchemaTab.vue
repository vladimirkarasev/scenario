<script setup lang="ts">
/* eslint-disable vue/no-mutating-props -- composable context objects passed as props, mutations are on reactive refs inside */
import type {useDirectoryVersions} from '@/modules/directories/composables/useDirectoryVersions'
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
import {Check, GripVertical, Loader2, Pencil, Plus, Trash2} from 'lucide-vue-next'
import {FILTER_OPERATORS} from '@/modules/directories/types/directory'
import {ref} from 'vue'

const props = defineProps<{
  versCtx: ReturnType<typeof useDirectoryVersions>
  canManage: boolean
  versionNumber: number | string
}>()

const emit = defineEmits<{ save: [] }>()

// ── Drag-and-drop reorder ────────────────────────────────────────────────────

const draggingIdx = ref<number | null>(null)
const dragOverIdx = ref<number | null>(null)

function onDragStart(e: DragEvent, idx: number): void {
  draggingIdx.value = idx
  if (e.dataTransfer) e.dataTransfer.effectAllowed = 'move'
}

function onDragOver(e: DragEvent, idx: number): void {
  e.preventDefault()
  dragOverIdx.value = idx
}

function onDrop(e: DragEvent, targetIdx: number): void {
  e.preventDefault()
  const from = draggingIdx.value
  draggingIdx.value = null
  dragOverIdx.value = null
  if (from === null || from === targetIdx) return

  const schema = [...props.versCtx.editableSchema.value]
  const [item] = schema.splice(from, 1)
  schema.splice(targetIdx, 0, item)
  schema.forEach((f, i) => {
    f.sort_order = i
  })
  props.versCtx.editableSchema.value = schema
}

function onDragEnd(): void {
  draggingIdx.value = null
  dragOverIdx.value = null
}
</script>

<template>
  <Card class="border-border/60">
    <CardHeader class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div class="space-y-1">
        <CardTitle>Схема колонок</CardTitle>
        <CardDescription>
          Определяет структуру данных версии
          <span class="font-mono">v{{ versionNumber }}</span>.
          Изменения применяются только к этой версии.
        </CardDescription>
      </div>
      <Button v-if="canManage" type="button" variant="outline" size="sm" class="gap-2 shrink-0"
              @click="props.versCtx.addSchemaField()">
        <Plus class="size-4"/>
        Добавить поле
      </Button>
    </CardHeader>
    <CardContent class="space-y-4">
      <div class="overflow-hidden rounded-xl border border-border/60">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead v-if="canManage" class="w-8 pr-0"/>
              <TableHead>Key</TableHead>
              <TableHead>Название</TableHead>
              <TableHead>Тип</TableHead>
              <TableHead class="text-center">Nullable</TableHead>
              <TableHead class="text-center">Фильтр</TableHead>
              <TableHead class="text-center">Поиск</TableHead>
              <TableHead class="w-20"/>
            </TableRow>
          </TableHeader>
          <TableBody @dragover.prevent>
            <TableRow
                v-for="(field, idx) in props.versCtx.editableSchema.value"
                :key="idx"
                :draggable="canManage"
                :class="[
                                draggingIdx === idx ? 'opacity-40' : '',
                                dragOverIdx === idx && draggingIdx !== idx ? 'border-t-2 border-primary' : '',
                            ]"
                @dragstart="onDragStart($event, idx)"
                @dragover="onDragOver($event, idx)"
                @drop="onDrop($event, idx)"
                @dragend="onDragEnd"
            >
              <TableCell v-if="canManage"
                         class="w-8 cursor-grab pr-0 text-muted-foreground/40 hover:text-muted-foreground active:cursor-grabbing">
                <GripVertical class="size-4"/>
              </TableCell>
              <TableCell class="font-mono text-sm">{{ field.key || '—' }}</TableCell>
              <TableCell class="text-sm">{{ field.name || '—' }}</TableCell>
              <TableCell>
                <Badge variant="outline" class="text-xs font-normal">{{ field.type }}</Badge>
              </TableCell>
              <TableCell class="text-center">
                <Check v-if="field.nullable" class="mx-auto size-4 text-muted-foreground"/>
              </TableCell>
              <TableCell class="text-center">
                <Check v-if="field.filterable" class="mx-auto size-4 text-primary"/>
              </TableCell>
              <TableCell class="text-center">
                <Check v-if="field.searchable" class="mx-auto size-4 text-primary"/>
              </TableCell>
              <TableCell>
                <div class="flex justify-end gap-1">
                  <Button
                      v-if="canManage"
                      type="button"
                      variant="ghost"
                      size="icon"
                      class="size-8"
                      @click="props.versCtx.openFieldModal(idx)"
                  >
                    <Pencil class="size-3.5"/>
                  </Button>
                  <Button
                      v-if="canManage"
                      type="button"
                      variant="ghost"
                      size="icon"
                      class="size-8 text-muted-foreground hover:text-destructive"
                      :disabled="props.versCtx.editableSchema.value.length === 1"
                      @click="props.versCtx.removeSchemaField(idx)"
                  >
                    <Trash2 class="size-3.5"/>
                  </Button>
                </div>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>

      <Separator/>

      <div class="grid gap-4 sm:grid-cols-2">
        <div class="space-y-2">
          <Label>Сортировка по умолчанию</Label>
          <p class="text-xs text-muted-foreground">Применяется при открытии таблицы, если пользователь не выбрал
            другую.</p>
          <div class="flex gap-2">
            <select
                :value="props.versCtx.editableDefaultSort.value.replace(/^-/, '')"
                class="flex h-10 w-full rounded-lg border border-input bg-background px-3 text-sm"
                :disabled="!canManage"
                @change="(e) => {
                                const field = (e.target as HTMLSelectElement).value
                                const dir = props.versCtx.editableDefaultSort.value.startsWith('-') ? '-' : ''
                                props.versCtx.editableDefaultSort.value = field ? dir + field : ''
                            }"
            >
              <option value="">Не задано</option>
              <option
                  v-for="f in props.versCtx.editableSchema.value.filter(f => f.key)"
                  :key="f.key"
                  :value="f.key"
              >
                {{ f.name || f.key }}
              </option>
            </select>
            <select
                v-if="props.versCtx.editableDefaultSort.value.replace(/^-/, '')"
                :value="props.versCtx.editableDefaultSort.value.startsWith('-') ? 'desc' : 'asc'"
                class="h-10 rounded-lg border border-input bg-background px-3 text-sm"
                :disabled="!canManage"
                @change="(e) => {
                                const field = props.versCtx.editableDefaultSort.value.replace(/^-/, '')
                                props.versCtx.editableDefaultSort.value = (e.target as HTMLSelectElement).value === 'desc' ? '-' + field : field
                            }"
            >
              <option value="asc">По возрастанию</option>
              <option value="desc">По убыванию</option>
            </select>
          </div>
        </div>
      </div>

      <Button
          v-if="canManage"
          :disabled="props.versCtx.schemaSaving.value"
          class="gap-2 self-start"
          @click="emit('save')"
      >
        <Loader2 v-if="props.versCtx.schemaSaving.value" class="size-4 animate-spin"/>
        {{ props.versCtx.schemaSaving.value ? 'Сохраняем...' : 'Сохранить схему' }}
      </Button>
    </CardContent>
  </Card>

  <!-- Field settings modal -->
  <Dialog v-model:open="props.versCtx.fieldModalOpen.value">
    <DialogContent class="sm:max-w-md">
      <DialogHeader>
        <DialogTitle>Настройка поля</DialogTitle>
        <DialogDescription>Укажите ключ, название и параметры фильтрации.</DialogDescription>
      </DialogHeader>
      <div class="grid gap-4 py-2">
        <div class="grid grid-cols-2 gap-3">
          <div class="space-y-1.5">
            <Label>Название</Label>
            <Input
                v-model="props.versCtx.fieldModalDraft.value.name"
                placeholder="Название поля"
                @input="props.versCtx.onFieldModalNameInput()"
            />
          </div>
          <div class="space-y-1.5">
            <Label>Key</Label>
            <Input
                v-model="props.versCtx.fieldModalDraft.value.key"
                placeholder="field_key"
                class="font-mono"
                @input="props.versCtx.onFieldModalKeyInput()"
            />
          </div>
        </div>
        <div class="space-y-1.5">
          <Label>Тип поля</Label>
          <NativeSelect v-model="props.versCtx.fieldModalDraft.value.type"
                        @change="props.versCtx.onFieldModalTypeChange()">
            <option value="string">Текст</option>
            <option value="integer">Число</option>
            <option value="boolean">Булево</option>
            <option value="date">Дата</option>
            <option value="datetime">Дата и время</option>
          </NativeSelect>
        </div>
        <div class="flex flex-wrap items-center gap-6">
          <label class="flex cursor-pointer items-center gap-2 text-sm">
            <input
                v-model="props.versCtx.fieldModalDraft.value.nullable"
                type="checkbox"
                class="size-4 rounded border-border accent-primary"
            />
            Необязательное (nullable)
          </label>
          <label class="flex cursor-pointer items-center gap-2 text-sm">
            <input
                v-model="props.versCtx.fieldModalDraft.value.filterable"
                type="checkbox"
                class="size-4 rounded border-border accent-primary"
            />
            Фильтруемое
          </label>
          <label class="flex cursor-pointer items-center gap-2 text-sm">
            <input
                v-model="props.versCtx.fieldModalDraft.value.searchable"
                type="checkbox"
                class="size-4 rounded border-border accent-primary"
            />
            Участвует в поиске
          </label>
        </div>

        <template v-if="props.versCtx.fieldModalDraft.value.filterable">
          <Separator/>
          <div class="space-y-1.5">
            <Label>Тип фильтра</Label>
            <NativeSelect v-model="props.versCtx.fieldModalDraft.value.filter_type"
                          @change="props.versCtx.onFieldModalFilterTypeChange()">
              <option value="string">Текст</option>
              <option value="integer">Число</option>
              <option value="boolean">Булево</option>
              <option value="date">Дата</option>
              <option value="datetime">Дата и время</option>
              <option value="list">Список (выпадающий)</option>
            </NativeSelect>
          </div>

          <template v-if="props.versCtx.fieldModalDraft.value.filter_type === 'list'">
            <label class="flex cursor-pointer items-center gap-2 text-sm">
              <input
                  v-model="props.versCtx.fieldModalDraft.value.filter_multiple"
                  type="checkbox"
                  class="size-4 rounded border-border accent-primary"
              />
              Множественный выбор
            </label>
            <div class="space-y-2">
              <Label>Предустановленные значения</Label>
              <p class="text-xs text-muted-foreground">Если заданы — фильтр показывает только их. Если пусто — строится
                из загруженных данных.</p>
              <div v-if="props.versCtx.fieldModalDraft.value.options?.length" class="flex flex-wrap gap-1.5">
                                <span
                                    v-for="(opt, idx) in props.versCtx.fieldModalDraft.value.options"
                                    :key="opt"
                                    class="inline-flex items-center gap-1 rounded-full border border-primary/30 bg-primary/10 px-2.5 py-0.5 text-xs text-primary"
                                >
                                    {{ opt }}
                                    <button
                                        type="button"
                                        class="ml-0.5 size-3 opacity-60 hover:opacity-100"
                                        @click="props.versCtx.removeFieldModalOption(idx)"
                                    >✕</button>
                                </span>
              </div>
              <div class="flex gap-2">
                <Input
                    v-model="props.versCtx.fieldModalNewOption.value"
                    placeholder="Новое значение..."
                    class="h-8 text-sm"
                    @keydown.enter.prevent="props.versCtx.addFieldModalOption()"
                />
                <Button type="button" variant="outline" size="sm" class="shrink-0"
                        @click="props.versCtx.addFieldModalOption()">
                  + Добавить
                </Button>
              </div>
            </div>
          </template>

          <template v-else-if="props.versCtx.fieldModalDraft.value.filter_type !== 'boolean'">
            <div class="space-y-1.5">
              <Label>Оператор фильтрации</Label>
              <NativeSelect v-model="props.versCtx.fieldModalDraft.value.filter_operator">
                <option
                    v-for="op in FILTER_OPERATORS[props.versCtx.fieldModalDraft.value.filter_type]"
                    :key="op.value"
                    :value="op.value"
                >
                  {{ op.label }}
                </option>
              </NativeSelect>
            </div>
            <div class="space-y-1.5">
              <Label>Подсказка (placeholder)</Label>
              <Input
                  v-model="props.versCtx.fieldModalDraft.value.filter_placeholder"
                  placeholder="Введите текст подсказки..."
              />
            </div>
          </template>
        </template>
      </div>
      <DialogFooter>
        <Button variant="outline" @click="props.versCtx.fieldModalOpen.value = false">Отмена</Button>
        <Button @click="props.versCtx.saveFieldModal()">Сохранить</Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
