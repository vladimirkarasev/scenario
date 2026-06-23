<script setup lang="ts">
import {
  Dialog, DialogContent, DialogHeader, DialogTitle,
} from '@/components/ui/dialog'
import {
  FormActions, FormBody, FormError, FormInput, FormSection, FormTextarea,
} from '@/components/form'
import type { useScenarioCreateModal } from '@/modules/scenario/composables/useScenarioCreateModal'

defineProps<{
    modal: ReturnType<typeof useScenarioCreateModal>
    currentSectionName?: string
}>()
</script>

<template>
    <Dialog v-model:open="modal.showModal.value">
        <DialogContent class="flex max-h-[90vh] flex-col p-0 sm:max-w-lg">
            <DialogHeader class="shrink-0 border-b border-slate-100 px-6 py-4">
                <DialogTitle class="text-[15px] font-bold text-slate-900">Новый сценарий</DialogTitle>
            </DialogHeader>
            <form class="flex min-h-0 flex-1 flex-col" novalidate @submit.prevent="modal.save">
                <FormBody>
                    <FormError :message="modal.formError.value" />
                    <FormSection>
                        <FormInput
                            v-model="modal.form.name"
                            name="name"
                            label="Название"
                            placeholder="Имя сценария"
                            required
                            :error="modal.errors.name"
                        />
                        <FormInput
                            v-model="modal.form.alias"
                            name="alias"
                            label="Alias"
                            placeholder="onboarding-hr"
                            hint="Уникальный идентификатор для запуска сценария по alias"
                            :error="modal.errors.alias"
                        />
                        <FormTextarea
                            v-model="modal.form.description"
                            name="description"
                            label="Описание"
                            :rows="3"
                            placeholder="Краткое описание сценария…"
                            :error="modal.errors.description"
                        />
                        <FormTextarea
                            v-model="modal.form.tags"
                            name="tags"
                            label="Tags"
                            :rows="2"
                            placeholder="hr, onboarding"
                            hint="Через запятую или перенос строки"
                            :error="modal.errors.tags"
                        />
                        <p v-if="currentSectionName" class="text-[11px] text-slate-500">
                            Сценарий будет создан в папке «<span class="font-medium text-slate-700">{{ currentSectionName }}</span>».
                        </p>
                    </FormSection>
                </FormBody>
                <FormActions
                    class="shrink-0"
                    :submitting="modal.submitting.value"
                    submit-label="Создать"
                    @cancel="modal.close()"
                    @submit="modal.save"
                />
            </form>
        </DialogContent>
    </Dialog>
</template>
