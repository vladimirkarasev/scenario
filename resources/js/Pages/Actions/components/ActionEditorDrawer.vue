<script setup lang="ts">
import AppEditorDrawer from '@/components/AppEditorDrawer.vue'
import TiptapTextEditor from '@/modules/scenario/components/tiptap/TiptapTextEditor.vue'
import {
  FormBody, FormError, FormField, FormInput, FormRow, FormSelect, FormTextarea, FormToggle,
} from '@/components/form'
import {Badge} from '@/components/ui/badge'
import {Button} from '@/components/ui/button'
import {Card, CardContent, CardDescription, CardHeader, CardTitle} from '@/components/ui/card'
import {Input} from '@/components/ui/input'
import {NativeSelect} from '@/components/ui/native-select'
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table'
import {Textarea} from '@/components/ui/textarea'
import Tabs from '@/components/ui/tabs/Tabs.vue'
import TabsContent from '@/components/ui/tabs/TabsContent.vue'
import TabsList from '@/components/ui/tabs/TabsList.vue'
import TabsTrigger from '@/components/ui/tabs/TabsTrigger.vue'
import {ArrowDown, ArrowUp, Check, Pencil, Plus, Trash2, Zap} from 'lucide-vue-next'
import {ref} from 'vue'
import SectionTreeSelect from '@/components/sections/SectionTreeSelect.vue'
import {actionCategoryRepository} from '@/modules/actions/repositories/actionCategoryRepository'
import type {useActionModal} from '@/modules/actions/composables/useActionModal'
import type {ActionTypeMeta} from '@/modules/actions/types/action'

const props = defineProps<{
  modal: ReturnType<typeof useActionModal>
  actionTypes: ActionTypeMeta[]
}>()

const inputFieldPlaceholderHint = '{{ key }}'

// Последнее сфокусированное поле параметров — туда вставляем input-переменную.
const focusedConfigKey = ref<string | null>(null)

function varToken(key: string): string {
  return `{{ ${key} }}`
}

function insertInputVar(key: string): void {
  const target = focusedConfigKey.value
  if (!target || !key) return
  const current = typeof props.modal.form.config[target] === 'string'
      ? props.modal.form.config[target] as string
      : ''
  props.modal.form.config[target] = `${current}${varToken(key)}`
}
</script>

<template>
  <AppEditorDrawer
      :open="modal.show.value"
      :title="modal.editingId.value ? 'Редактировать action' : 'Новый action'"
      :subtitle="modal.editingId.value ? (modal.form.code || modal.form.slug) : 'Создание нового действия'"
      width-class="!w-[760px] !max-w-[95vw]"
      icon-bg-class="bg-blue-100"
      :saving="modal.saving.value"
      :can-save="Boolean(modal.form.name.trim() && modal.form.slug.trim())"
      @update:open="(v: boolean) => !v && modal.close()"
      @cancel="modal.close"
      @save="modal.save"
  >
    <template #icon>
      <Zap class="size-3.5 text-blue-600"/>
    </template>

    <Tabs default-value="general" class="flex flex-1 flex-col overflow-hidden">
      <TabsList class="mx-6 mt-4 w-fit">
        <TabsTrigger value="general">Основное</TabsTrigger>
        <TabsTrigger value="config">Параметры handler-а</TabsTrigger>
        <TabsTrigger value="inputs">
          Input-поля
          <span v-if="modal.form.input_fields.length"
                class="ml-1.5 rounded-full bg-blue-100 px-1.5 text-[10px] font-semibold text-blue-700">{{
              modal.form.input_fields.length
            }}</span>
        </TabsTrigger>
      </TabsList>

      <div class="mx-6 mt-3">
        <FormError :message="modal.error.value"/>
      </div>

      <TabsContent value="general" class="flex flex-1 overflow-hidden">
        <FormBody>
          <FormRow>
            <FormInput :model-value="modal.form.name" label="Название" placeholder="Отправка email клиенту" required
                       :error="modal.errors.name" @update:model-value="modal.onNameInput"/>
            <FormInput :model-value="modal.form.slug" label="Slug" placeholder="send-email-to-client" required
                       hint="Внутренний идентификатор (URL-friendly)." :error="modal.errors.slug"
                       @update:model-value="modal.onSlugInput"/>
          </FormRow>
          <FormSelect
              v-model="modal.form.type"
              label="Тип"
              hint="При смене типа подгружаются параметры handler-а; code будет проставлен по умолчанию."
              :error="modal.errors.type"
              @update:model-value="modal.onTypeChange"
          >
            <option v-for="meta in actionTypes" :key="meta.value" :value="meta.value">{{
                meta.label
              }}
            </option>
          </FormSelect>
          <FormTextarea v-model="modal.form.description" label="Описание" placeholder="Краткое описание для коллег"
                        :rows="3" :error="modal.errors.description"/>

          <FormField v-if="modal.editingId.value" label="Разделы">
            <SectionTreeSelect
                v-model="modal.form.category_ids"
                :load-all="() => actionCategoryRepository.all()"
                :open="modal.show.value"
            />
          </FormField>
          <FormToggle
              v-model="modal.form.is_active"
              label="Активно"
              description="Отключённое action нельзя вызвать из сценария"
          />
        </FormBody>
      </TabsContent>

      <TabsContent value="config" class="flex flex-1 overflow-hidden">
        <FormBody>
          <div
              v-if="modal.form.input_fields.length && modal.fieldsFor(modal.form.type).length"
              class="flex flex-wrap items-center gap-1.5 rounded-xl border border-blue-100 bg-blue-50/50 px-3 py-2"
          >
            <span class="text-[11px] text-slate-500">Вставить input-переменную:</span>
            <button
                v-for="f in modal.form.input_fields"
                :key="f.key"
                type="button"
                :disabled="!focusedConfigKey"
                class="rounded-md border border-blue-200 bg-white px-1.5 py-0.5 font-mono text-[11px] text-blue-700 transition hover:bg-blue-100 disabled:cursor-not-allowed disabled:opacity-50"
                :title="focusedConfigKey ? `Вставить в поле «${focusedConfigKey}»` : 'Сначала кликните в поле параметра'"
                @click="insertInputVar(f.key)"
            >{{ varToken(f.key) }}
            </button>
          </div>

          <template v-if="modal.fieldsFor(modal.form.type).length">
            <FormField
                v-for="field in modal.fieldsFor(modal.form.type)" :key="field.key"
                :label="field.label"
                :required="field.required"
                :hint="field.description ?? undefined"
                @focusin="focusedConfigKey = field.key"
            >
              <Input
                  v-if="field.type === 'string' || field.type === 'email_list'"
                  v-model="modal.form.config[field.key] as string"
                  :placeholder="field.placeholder ?? ''"
              />
              <Textarea
                  v-else-if="field.type === 'text' || field.type === 'code'"
                  v-model="modal.form.config[field.key] as string"
                  rows="5"
                  :placeholder="field.placeholder ?? ''"
                  :class="field.type === 'code' ? 'font-mono text-[12px]' : ''"
              />
              <TiptapTextEditor
                  v-else-if="field.type === 'html'"
                  :model-value="(modal.form.config[field.key] as string) ?? ''"
                  format="html"
                  :placeholder="field.placeholder ?? 'Введите HTML...'"
                  min-height="min-h-40"
                  @update:model-value="(v: unknown) => modal.form.config[field.key] = v"
              />
              <Input
                  v-else-if="field.type === 'number'"
                  v-model.number="modal.form.config[field.key] as number"
                  type="number"
                  :placeholder="field.placeholder ?? ''"
              />
              <FormToggle
                  v-else-if="field.type === 'boolean'"
                  :model-value="Boolean(modal.form.config[field.key])"
                  :label="field.label"
                  :description="field.description ?? ''"
                  @update:model-value="modal.form.config[field.key] = $event"
              />
              <NativeSelect
                  v-else-if="field.type === 'select'"
                  v-model="modal.form.config[field.key] as string"
              >
                <option v-for="opt in field.options" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </NativeSelect>
              <Input
                  v-else
                  v-model="modal.form.config[field.key] as string"
                  :placeholder="field.placeholder ?? ''"
              />
            </FormField>
          </template>

          <div v-else class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-[12px] text-amber-700">
            Для этого типа action handler не описал параметры.
          </div>
        </FormBody>
      </TabsContent>

      <TabsContent value="inputs" class="flex flex-1 overflow-hidden">
        <FormBody>
          <Card class="border-border/60">
            <CardHeader class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
              <div class="space-y-1">
                <CardTitle>Поля input</CardTitle>
                <CardDescription>
                  Эти поля будет передавать сценарий при вызове action. Доступны в шаблонах config как
                  <span class="font-mono">{{ inputFieldPlaceholderHint }}</span>.
                </CardDescription>
              </div>
              <Button type="button" variant="outline" size="sm" class="gap-2 shrink-0"
                      @click="modal.openFieldModal(null)">
                <Plus class="size-4"/>
                Добавить поле
              </Button>
            </CardHeader>
            <CardContent class="space-y-4">
              <div class="overflow-hidden rounded-xl border border-border/60">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Key</TableHead>
                      <TableHead>Название</TableHead>
                      <TableHead>Тип</TableHead>
                      <TableHead class="text-center">Обяз.</TableHead>
                      <TableHead class="w-28"/>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    <TableRow v-for="(field, idx) in modal.form.input_fields" :key="idx">
                      <TableCell class="font-mono text-sm">{{ field.key || '—' }}</TableCell>
                      <TableCell class="text-sm">{{ field.label || '—' }}</TableCell>
                      <TableCell>
                        <Badge variant="outline" class="text-xs font-normal">
                          {{ modal.inputFieldTypes.find(t => t.value === field.type)?.label ?? field.type }}
                        </Badge>
                      </TableCell>
                      <TableCell class="text-center">
                        <Check v-if="field.required" class="mx-auto size-4 text-primary"/>
                      </TableCell>
                      <TableCell>
                        <div class="flex justify-end gap-1">
                          <Button type="button" variant="ghost" size="icon"
                                  class="size-8 text-muted-foreground hover:text-foreground" :disabled="idx === 0"
                                  @click="modal.moveInputField(idx, -1)">
                            <ArrowUp class="size-3.5"/>
                          </Button>
                          <Button type="button" variant="ghost" size="icon"
                                  class="size-8 text-muted-foreground hover:text-foreground"
                                  :disabled="idx === modal.form.input_fields.length - 1"
                                  @click="modal.moveInputField(idx, 1)">
                            <ArrowDown class="size-3.5"/>
                          </Button>
                          <Button type="button" variant="ghost" size="icon" class="size-8"
                                  @click="modal.openFieldModal(idx)">
                            <Pencil class="size-3.5"/>
                          </Button>
                          <Button type="button" variant="ghost" size="icon"
                                  class="size-8 text-muted-foreground hover:text-destructive"
                                  @click="modal.removeInputField(idx)">
                            <Trash2 class="size-3.5"/>
                          </Button>
                        </div>
                      </TableCell>
                    </TableRow>
                  </TableBody>
                </Table>
                <div v-if="!modal.form.input_fields.length"
                     class="px-4 py-8 text-center text-[12px] text-muted-foreground">
                  Полей пока нет. Action не будет требовать данные при вызове.
                </div>
              </div>
            </CardContent>
          </Card>
        </FormBody>
      </TabsContent>
    </Tabs>
  </AppEditorDrawer>
</template>
