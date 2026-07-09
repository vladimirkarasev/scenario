<script setup lang="ts">
import {FormActions, FormBody, FormError, FormJsonInput} from '@/components/form'
import {X} from 'lucide-vue-next'
import type {useActionRunModal} from '@/modules/actions/composables/useActionRunModal'
import {toRef} from 'vue'

const props = defineProps<{
  runModal: ReturnType<typeof useActionRunModal>
}>()

const runModal = toRef(props, 'runModal')
</script>

<template>
  <Teleport to="body">
    <div v-if="runModal.show.value"
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-sm"
         @click.self="runModal.close">
      <div
          class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_24px_64px_-12px_rgba(15,23,42,0.2)]">
        <div class="flex shrink-0 items-center justify-between border-b border-slate-100 px-6 py-4">
          <div class="text-[15px] font-bold text-slate-900">Запуск: {{ runModal.action.value?.name }}</div>
          <button class="text-slate-400 transition hover:text-slate-700" @click="runModal.close">
            <X :size="18" />
          </button>
        </div>
        <form class="contents" novalidate @submit.prevent="runModal.run">
          <FormBody>
            <FormError :message="runModal.error.value" />
            <FormJsonInput
                v-model="runModal.form.input"
                label="Input JSON"
                :rows="8"
                :error="runModal.errors.input"
            />
          </FormBody>
          <FormActions
              :submitting="runModal.saving.value"
              :submit-label="runModal.saving.value ? 'Запускаем…' : 'Запустить'"
              @cancel="runModal.close"
              @submit="runModal.run"
          />
        </form>
      </div>
    </div>
  </Teleport>
</template>
