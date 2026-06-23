<script setup lang="ts">
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog'
import {Button} from '@/components/ui/button'
import {Separator} from '@/components/ui/separator'
import {FormCheckbox, FormError, FormInput, FormRow, FormSelect} from '@/components/form'
import type {useActionModal} from '@/modules/actions/composables/useActionModal'

defineProps<{
  modal: ReturnType<typeof useActionModal>
}>()
</script>

<template>
  <Dialog v-model:open="modal.fieldModalOpen.value">
    <DialogContent class="sm:max-w-md">
      <DialogHeader>
        <DialogTitle>{{ modal.fieldModalIdx.value === null ? 'Новое поле input' : 'Настройка поля' }}</DialogTitle>
        <DialogDescription>Укажите ключ, название и тип значения, которое сценарий должен передать.</DialogDescription>
      </DialogHeader>
      <div class="space-y-4 py-2">
        <FormError :message="modal.fieldModalError.value"/>
        <FormRow>
          <FormInput v-model="modal.fieldModalDraft.label" label="Название" placeholder="UUID клиента"/>
          <FormInput v-model="modal.fieldModalDraft.key" label="Key" placeholder="client_uuid" required/>
        </FormRow>
        <FormSelect v-model="modal.fieldModalDraft.type" label="Тип поля">
          <option v-for="opt in modal.inputFieldTypes" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
        </FormSelect>
        <Separator/>
        <FormCheckbox v-model="modal.fieldModalDraft.required" label="Обязательное поле"/>
      </div>
      <DialogFooter>
        <Button variant="outline" @click="modal.fieldModalOpen.value = false">Отмена</Button>
        <Button @click="modal.saveFieldModal()">Сохранить</Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
