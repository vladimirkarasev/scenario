<script setup lang="ts">
import {Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle} from '@/components/ui/dialog'
import {Button} from '@/components/ui/button'
import type {ScenarioFieldPreset} from '@/modules/scenario/types/field-preset'

defineProps<{
  open: boolean
  preset: ScenarioFieldPreset | null
  deleting?: boolean
}>()

const emit = defineEmits<{
  'update:open': [boolean]
  confirm: []
}>()
</script>

<template>
  <Dialog :open="open" @update:open="emit('update:open', $event)">
    <DialogContent class="sm:max-w-md">
      <DialogHeader>
        <DialogTitle>Удалить пользовательское поле?</DialogTitle>
        <DialogDescription>
          Шаблон «{{ preset?.name }}» исчезнет из палитры. Уже добавленные в блоки поля останутся без изменений.
        </DialogDescription>
      </DialogHeader>
      <DialogFooter>
        <Button variant="outline" :disabled="deleting" @click="emit('update:open', false)">Отмена</Button>
        <Button variant="destructive" :disabled="deleting" @click="emit('confirm')">
          {{ deleting ? 'Удаление…' : 'Удалить' }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
