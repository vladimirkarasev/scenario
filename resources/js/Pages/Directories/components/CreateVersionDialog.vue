<script setup lang="ts">
/* eslint-disable vue/no-mutating-props -- versCtx is a composable API whose refs are intentionally controlled by this dialog */
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog'
import {Button} from '@/components/ui/button'
import {Copy, Loader2} from 'lucide-vue-next'
import type {useDirectoryVersions} from '@/modules/directories/composables/useDirectoryVersions'

defineProps<{
  versCtx: ReturnType<typeof useDirectoryVersions>
  flash: (msg: string) => void
}>()
</script>

<template>
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
        <Button :disabled="versCtx.createLoading.value" @click="versCtx.createVersion(flash)">
          <Loader2 v-if="versCtx.createLoading.value" class="mr-2 size-4 animate-spin"/>
          Создать
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
