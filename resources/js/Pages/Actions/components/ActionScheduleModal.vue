<script setup lang="ts">
import { Button } from '@/components/ui/button'
import {
    FormActions, FormBody, FormCronInput, FormError, FormInput,
    FormJsonInput, FormRow, FormToggle,
} from '@/components/form'
import { Loader2, X } from 'lucide-vue-next'
import type { useActionScheduleModal } from '@/modules/actions/composables/useActionScheduleModal'

defineProps<{
    scheduleModal: ReturnType<typeof useActionScheduleModal>
}>()
</script>

<template>
    <Teleport to="body">
        <div v-if="scheduleModal.show.value" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-sm" @click.self="scheduleModal.close">
            <div class="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_24px_64px_-12px_rgba(15,23,42,0.2)]">
                <div class="flex shrink-0 items-center justify-between border-b border-slate-100 px-6 py-4">
                    <div class="text-[15px] font-bold text-slate-900">Расписание: {{ scheduleModal.action.value?.name }}</div>
                    <button class="text-slate-400 transition hover:text-slate-700" @click="scheduleModal.close"><X :size="18" /></button>
                </div>
                <form class="contents" novalidate @submit.prevent="scheduleModal.save">
                    <FormBody>
                        <div v-if="scheduleModal.loading.value" class="flex items-center justify-center gap-2 py-10 text-sm text-slate-500">
                            <Loader2 :size="18" class="animate-spin" />
                            Загрузка расписания...
                        </div>
                        <template v-else>
                        <FormError :message="scheduleModal.error.value" />
                        <FormToggle v-model="scheduleModal.form.enabled" label="Расписание активно" />
                        <div class="grid gap-4 md:grid-cols-[1fr_180px]">
                            <FormCronInput
                                v-model="scheduleModal.form.cron"
                                label="Cron-выражение"
                                :presets="scheduleModal.presets.map(p => ({ label: p.label, value: p.cron }))"
                                required
                                :error="scheduleModal.errors.cron"
                            />
                            <FormInput
                                v-model="scheduleModal.form.timezone"
                                label="Timezone"
                                required
                                :error="scheduleModal.errors.timezone"
                            />
                        </div>
                        <FormRow>
                            <FormJsonInput v-model="scheduleModal.form.input"   label="Input JSON"   :rows="7" :error="scheduleModal.errors.input" />
                            <FormJsonInput v-model="scheduleModal.form.options" label="Options JSON" :rows="7" :error="scheduleModal.errors.options" />
                        </FormRow>
                        <FormJsonInput
                            v-model="scheduleModal.form.settings"
                            label="Settings JSON"
                            :rows="4"
                            :error="scheduleModal.errors.settings"
                        />
                        </template>
                    </FormBody>
                    <FormActions
                        :submitting="scheduleModal.saving.value || scheduleModal.loading.value"
                        @cancel="scheduleModal.close"
                        @submit="scheduleModal.save"
                    >
                        <template #extra>
                            <Button
                                v-if="scheduleModal.action.value?.schedule && !scheduleModal.loading.value"
                                type="button"
                                variant="outline"
                                class="border-red-200 text-red-600 hover:bg-red-50"
                                :disabled="scheduleModal.saving.value"
                                @click="scheduleModal.remove"
                            >
                                Удалить
                            </Button>
                        </template>
                    </FormActions>
                </form>
            </div>
        </div>
    </Teleport>
</template>
