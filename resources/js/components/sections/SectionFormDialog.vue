<script setup lang="ts">
import {
  Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog'
import {FormError, FormInput} from '@/components/form'
import {Button} from '@/components/ui/button'
import {Label} from '@/components/ui/label'
import {computed} from 'vue'
import {Check, Pencil, FolderPlus, Plus, RefreshCw} from 'lucide-vue-next'
import type {SectionModalApi} from '@/composables/useSectionModal'

const props = withDefaults(defineProps<{
  modal: SectionModalApi
  parentOptions?: { id: string; name: string; depth: number }[]
  excludeIds?: string[]
  placeholder?: string
  showParentSelect?: boolean
}>(), {
  parentOptions: () => [],
  excludeIds: () => [],
  placeholder: 'Например: CRM',
  showParentSelect: true,
})

const availableParents = computed(() =>
    props.parentOptions.filter(s =>
        s.id !== props.modal.editingId.value && !props.excludeIds.includes(s.id),
    ),
)
</script>

<template>
  <Dialog v-model:open="modal.open.value">
    <DialogContent class="sm:max-w-md">
      <DialogHeader>
        <DialogTitle class="flex items-center gap-2">
          <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
            <Pencil v-if="modal.isEditing.value" :size="14"/>
            <FolderPlus v-else :size="14"/>
          </div>
          {{ modal.isEditing.value ? 'Редактировать раздел' : 'Новый раздел' }}
        </DialogTitle>
      </DialogHeader>

      <form class="space-y-4" novalidate @submit.prevent="modal.submit()">
        <FormError :message="modal.formError.value"/>
        <FormInput
            v-model="modal.form.name"
            label="Название"
            :placeholder="placeholder"
            required
            :error="modal.errors.name"
            @keydown.enter="modal.submit()"
        />

        <div v-if="showParentSelect && modal.isEditing.value" class="space-y-1.5">
          <Label>Родительский раздел</Label>
          <div class="max-h-48 overflow-y-auto rounded-md border">
            <button
                type="button"
                class="flex w-full items-center gap-2.5 border-b px-3 py-2 text-sm transition last:border-0 hover:bg-muted/40"
                @click="modal.form.parent_id = null"
            >
              <div
                  class="flex size-4 flex-none items-center justify-center rounded-full border transition"
                  :class="modal.form.parent_id === null ? 'border-primary bg-primary' : 'border-border bg-background'"
              >
                <Check v-if="modal.form.parent_id === null" class="size-2.5 text-primary-foreground"/>
              </div>
              <span
                  class="select-none"
                  :class="modal.form.parent_id === null ? 'font-medium text-foreground' : 'text-muted-foreground'"
              >— Корневой раздел</span>
            </button>
            <button
                v-for="s in availableParents"
                :key="s.id"
                type="button"
                class="flex w-full items-center gap-2.5 border-b px-3 py-2 text-sm transition last:border-0 hover:bg-muted/40"
                :style="{ paddingLeft: `${12 + s.depth * 14}px` }"
                @click="modal.form.parent_id = s.id"
            >
              <div
                  class="flex size-4 flex-none items-center justify-center rounded-full border transition"
                  :class="modal.form.parent_id === s.id ? 'border-primary bg-primary' : 'border-border bg-background'"
              >
                <Check v-if="modal.form.parent_id === s.id" class="size-2.5 text-primary-foreground"/>
              </div>
              <span
                  class="select-none truncate"
                  :class="modal.form.parent_id === s.id ? 'font-medium text-foreground' : 'text-muted-foreground'"
              >{{ s.name }}</span>
            </button>
          </div>
        </div>

        <!-- Доп. поля раздела (например, группы доступа и флаги в сценариях). -->
        <slot/>

        <DialogFooter>
          <Button type="button" variant="outline" @click="modal.close()">Отмена</Button>
          <Button type="submit" :disabled="modal.submitting.value" class="gap-1.5">
            <RefreshCw v-if="modal.submitting.value" :size="13" class="animate-spin"/>
            <Pencil v-else-if="modal.isEditing.value" :size="13"/>
            <Plus v-else :size="13"/>
            {{ modal.isEditing.value ? 'Сохранить' : 'Создать' }}
          </Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>
