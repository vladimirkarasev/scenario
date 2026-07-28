<script setup lang="ts">
/* eslint-disable vue/no-mutating-props -- composable context objects passed as props, mutations are on reactive refs inside */
import type {DirectoryProxyPickerContext} from '@/modules/directories/composables/useDirectoryProxyPicker'
import type {DirectorySchemaField, DirectoryVersionSyncOptions} from '@/modules/directories/types/directory'
import {Button} from '@/components/ui/button'
import {
  Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle,
} from '@/components/ui/dialog'
import {Input} from '@/components/ui/input'
import {Separator} from '@/components/ui/separator'
import {Table, TableBody, TableCell, TableHead, TableHeader, TableRow} from '@/components/ui/table'
import {Badge} from '@/components/ui/badge'
import {Check, Globe, KeyRound, Loader2, Plus, Search} from 'lucide-vue-next'
import {computed} from 'vue'

const props = defineProps<{
  schemaFields: DirectorySchemaField[]
  fieldMapping: Record<string, string>
  proxyPicker: DirectoryProxyPickerContext
  syncOpts: DirectoryVersionSyncOptions
  externalKeyField?: string | null
  matchBy?: string | null
  saving: boolean
  saveError: string | null
  canManage: boolean
  showSyncOptions?: boolean
}>()
const emit = defineEmits<{ save: []; 'update:externalKeyField': [value: string | null] }>()

const selectedExternalKeyField = computed<string>({
  get: () => props.externalKeyField
      ?? (props.matchBy ? props.fieldMapping[props.matchBy] ?? '' : ''),
  set: value => emit('update:externalKeyField', value || null),
})

const recommendedMatchByProxyField = computed(() =>
    props.proxyPicker.fields.value.find(field => field.identity) ?? null,
)

function useRecommendedExternalKey(): void {
  const field = recommendedMatchByProxyField.value
  if (field) selectedExternalKeyField.value = field.key
}
</script>

<template>
  <!-- Proxy selector -->
  <div class="space-y-3">
    <div>
      <div class="text-sm font-medium">Прокси</div>
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
      <Button v-if="canManage" variant="ghost" size="sm" class="shrink-0 text-xs" @click="proxyPicker.openPicker()">
        Изменить
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

  <!-- Field mapping -->
  <template v-if="proxyPicker.selected.value">
    <Separator/>
    <div class="space-y-3">
      <div>
        <div class="text-sm font-medium">Сопоставление полей</div>
        <div class="text-xs text-muted-foreground">Соотнесите поля справочника с полями, которые возвращает прокси</div>
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
                    v-model="fieldMapping[f.key]"
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

  <!-- Sync options -->
  <template v-if="showSyncOptions !== false">
    <Separator/>
    <div class="space-y-3">
      <div>
        <div class="text-sm font-medium">Внешний ключ (external_key)</div>
        <div class="text-xs text-muted-foreground">Уникальное поле Proxy, по которому существующие записи обновляются,
          а отсутствующие определяются при синхронизации.
        </div>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <select
            v-model="selectedExternalKeyField"
            class="flex h-10 w-full rounded-lg border border-input bg-background px-3 text-sm sm:max-w-xs"
            :disabled="!canManage"
        >
          <option value="">Не задано (по хэшу строки)</option>
          <option v-for="pf in proxyPicker.fields.value" :key="pf.key" :value="pf.key">
            {{ pf.label }}{{ pf.identity ? ' · рекомендуется' : '' }}
          </option>
        </select>
        <Button
            v-if="recommendedMatchByProxyField && selectedExternalKeyField !== recommendedMatchByProxyField.key"
            type="button"
            variant="outline"
            size="sm"
            class="gap-1.5"
            :disabled="!canManage"
            @click="useRecommendedExternalKey"
        >
          <KeyRound class="size-3.5"/>
          Выбрать рекомендуемое
        </Button>
      </div>
      <p v-if="!proxyPicker.fields.value.length" class="text-xs text-muted-foreground">
        Выберите Proxy, чтобы загрузить доступные поля ответа.
      </p>
      <p v-else-if="selectedExternalKeyField" class="text-xs text-muted-foreground">
        Поле не обязательно добавлять в схему: значение сохранится только в
        <code>DirectoryItem.external_key</code>.
      </p>
    </div>
    <Separator/>
    <div class="space-y-3">
      <div>
        <div class="text-sm font-medium">Режим синхронизации</div>
        <div class="text-xs text-muted-foreground">Выберите операции, которые будут выполнены при каждой синхронизации
        </div>
      </div>
      <div class="grid grid-cols-3 gap-3">
        <label
            class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition"
            :class="syncOpts.add_new ? 'border-primary bg-primary/5' : 'border-border/60 hover:border-primary/30'"
            @click="syncOpts.add_new = !syncOpts.add_new"
        >
          <div class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded border transition"
               :class="syncOpts.add_new ? 'border-primary bg-primary text-primary-foreground' : 'border-border/60'">
            <Check v-if="syncOpts.add_new" class="size-2.5"/>
          </div>
          <div>
            <div class="font-medium">Добавить новые</div>
            <div class="text-xs text-muted-foreground">Строки из источника, которых ещё нет в справочнике</div>
          </div>
        </label>
        <label
            class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition"
            :class="syncOpts.update_existing ? 'border-primary bg-primary/5' : 'border-border/60 hover:border-primary/30'"
            @click="syncOpts.update_existing = !syncOpts.update_existing"
        >
          <div class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded border transition"
               :class="syncOpts.update_existing ? 'border-primary bg-primary text-primary-foreground' : 'border-border/60'">
            <Check v-if="syncOpts.update_existing" class="size-2.5"/>
          </div>
          <div>
            <div class="font-medium">Обновить текущие</div>
            <div class="text-xs text-muted-foreground">Перезаписать поля у существующих записей по ключевому полю</div>
          </div>
        </label>
        <label
            class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition"
            :class="syncOpts.delete_unused ? 'border-destructive/40 bg-destructive/5' : 'border-border/60 hover:border-primary/30'"
            @click="syncOpts.delete_unused = !syncOpts.delete_unused"
        >
          <div class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded border transition"
               :class="syncOpts.delete_unused ? 'border-destructive bg-destructive text-destructive-foreground' : 'border-border/60'">
            <Check v-if="syncOpts.delete_unused" class="size-2.5"/>
          </div>
          <div>
            <div class="font-medium">Удалить неиспользованные</div>
            <div class="text-xs text-muted-foreground">Удалить записи, которых нет в источнике</div>
          </div>
        </label>
      </div>
    </div>
  </template>

  <div v-if="saveError"
       class="rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
    {{ saveError }}
  </div>

  <Button v-if="canManage" :disabled="saving" class="gap-2 self-start" @click="emit('save')">
    <Loader2 v-if="saving" class="size-4 animate-spin"/>
    {{ saving ? 'Сохраняем...' : 'Сохранить' }}
  </Button>

  <!-- Proxy picker dialog -->
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
            :class="proxyPicker.selected.value?.uuid === proxy.uuid ? 'bg-primary/5 ring-1 ring-primary/30' : ''"
            @click="proxyPicker.selectProxy(proxy)"
        >
          <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted/40 text-muted-foreground">
            <Globe class="size-4"/>
          </div>
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
              <span class="truncate font-medium text-foreground">{{ proxy.name }}</span>
              <Badge v-if="!proxy.is_active" variant="secondary" class="shrink-0 text-xs">неактивен</Badge>
              <Check v-if="proxyPicker.selected.value?.uuid === proxy.uuid"
                     class="ml-auto size-4 shrink-0 text-primary"/>
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
</template>
