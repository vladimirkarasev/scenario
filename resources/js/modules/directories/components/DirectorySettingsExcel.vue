<script setup lang="ts">
/* eslint-disable vue/no-mutating-props -- composable context objects passed as props, mutations are on reactive refs inside */
import {Check} from 'lucide-vue-next'

defineProps<{
  options: { addNew: boolean; updateExisting: boolean; deleteUnused: boolean }
  canManage: boolean
}>()
</script>

<template>
  <div class="space-y-3">
    <div>
      <div class="text-sm font-medium">Режим синхронизации</div>
      <div class="text-xs text-muted-foreground">Выберите операции, которые будут выполнены при каждом импорте файла
      </div>
    </div>
    <label
        class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition"
        :class="options.addNew ? 'border-primary bg-primary/5' : 'border-border/60 hover:border-primary/30'"
        @click="canManage && (options.addNew = !options.addNew)"
    >
      <div class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded border transition"
           :class="options.addNew ? 'border-primary bg-primary text-primary-foreground' : 'border-border/60'">
        <Check v-if="options.addNew" class="size-2.5"/>
      </div>
      <div>
        <div class="font-medium">Добавить новые</div>
        <div class="text-xs text-muted-foreground">Строки из файла, которых ещё нет в справочнике</div>
      </div>
    </label>
    <label
        class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition"
        :class="options.updateExisting ? 'border-primary bg-primary/5' : 'border-border/60 hover:border-primary/30'"
        @click="canManage && (options.updateExisting = !options.updateExisting)"
    >
      <div class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded border transition"
           :class="options.updateExisting ? 'border-primary bg-primary text-primary-foreground' : 'border-border/60'">
        <Check v-if="options.updateExisting" class="size-2.5"/>
      </div>
      <div>
        <div class="font-medium">Обновить текущие</div>
        <div class="text-xs text-muted-foreground">Перезаписать поля у существующих записей по хэш-ключу строки</div>
      </div>
    </label>
    <label
        class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition"
        :class="options.deleteUnused ? 'border-destructive/40 bg-destructive/5' : 'border-border/60 hover:border-primary/30'"
        @click="canManage && (options.deleteUnused = !options.deleteUnused)"
    >
      <div class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded border transition"
           :class="options.deleteUnused ? 'border-destructive bg-destructive text-destructive-foreground' : 'border-border/60'">
        <Check v-if="options.deleteUnused" class="size-2.5"/>
      </div>
      <div>
        <div class="font-medium">Удалить неиспользованные</div>
        <div class="text-xs text-muted-foreground">Удалить записи, которых нет в файле</div>
      </div>
    </label>
  </div>
</template>
