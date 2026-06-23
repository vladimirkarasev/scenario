<script setup lang="ts">
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { FormActions, FormError, FormInput, FormSelect } from '@/components/form'
import type { ScenarioInputField } from '@/modules/scenario/types/scenario'

defineProps<{
    open: boolean
    isEditing: boolean
    draft: ScenarioInputField
    error: string | null
}>()

const emit = defineEmits<{
    'update:open': [v: boolean]
    submit: []
}>()
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ isEditing ? 'Редактирование поля' : 'Новое поле запуска' }}</DialogTitle>
            </DialogHeader>
            <form class="space-y-4 py-2" novalidate @submit.prevent="emit('submit')">
                <FormError :message="error" />
                <FormInput
                    v-model="draft.label"
                    name="label"
                    label="Название"
                    placeholder="Дата старта"
                />
                <FormInput
                    v-model="draft.key"
                    name="key"
                    label="Ключ"
                    placeholder="start_date"
                    required
                    autocomplete="off"
                />
                <FormSelect
                    v-model="draft.type"
                    name="type"
                    label="Тип значения"
                >
                    <option value="text">Текст</option>
                    <option value="datetime">Дата и время</option>
                    <option value="json">JSON</option>
                    <option value="boolean">Boolean</option>
                </FormSelect>
                <FormActions
                    submit-label="Сохранить"
                    @cancel="emit('update:open', false)"
                    @submit="emit('submit')"
                />
            </form>
        </DialogContent>
    </Dialog>
</template>
