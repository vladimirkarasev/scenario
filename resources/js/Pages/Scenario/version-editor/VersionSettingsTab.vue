<script setup lang="ts">
/* eslint-disable vue/no-mutating-props -- form is shared editor state intentionally edited by this tab */
import {Card, CardContent, CardHeader, CardTitle} from '@/components/ui/card'
import {Button} from '@/components/ui/button'
import {FormError, FormInput} from '@/components/form'
import {Check, Save} from 'lucide-vue-next'

type VersionStatus = 'active' | 'draft' | 'archived'

interface StatusConfig {
  label: string
  dot: string
  text: string
  ring: string
}

defineProps<{
  form: {
    name: string
    status: VersionStatus
  }
  errors: { name?: string }
  saveError: string | null
  saving: boolean
  versionCreatedAt: string | null
  versionUpdatedAt: string | null
  statusConfig: Record<VersionStatus, StatusConfig>
  formatDate: (iso: string | null) => string
}>()

const emit = defineEmits<{
  save: []
}>()

const STATUSES: VersionStatus[] = ['active', 'draft', 'archived']
</script>

<template>
  <Card class="border-border/60">
    <CardHeader class="pb-3">
      <CardTitle class="text-sm">Основное</CardTitle>
    </CardHeader>
    <CardContent class="space-y-4">
      <FormError :message="saveError"/>
      <FormInput
          v-model="form.name"
          label="Название версии"
          placeholder="Например: v1 · Начальная версия"
          :error="errors.name"
      />
      <div class="flex flex-wrap gap-x-5 gap-y-1 text-xs text-muted-foreground">
        <span>Создан: {{ formatDate(versionCreatedAt) }}</span>
        <span>Обновлён: {{ formatDate(versionUpdatedAt) }}</span>
      </div>
    </CardContent>
  </Card>

  <Card class="border-border/60">
    <CardHeader class="pb-3">
      <CardTitle class="text-sm">Статус</CardTitle>
    </CardHeader>
    <CardContent class="space-y-1.5">
      <button
          v-for="s in STATUSES"
          :key="s"
          type="button"
          class="flex w-full items-center gap-3 rounded-lg border px-3 py-2.5 text-left text-sm transition"
          :class="form.status === s
                    ? 'border-primary bg-primary/5 ring-1 ring-primary/20'
                    : 'border-border hover:border-border/80 hover:bg-muted/40'"
          @click="form.status = s"
      >
        <div
            class="flex size-4 flex-none items-center justify-center rounded-full border transition"
            :class="form.status === s ? 'border-primary bg-primary' : 'border-border bg-background'"
        >
          <Check v-if="form.status === s" class="size-2.5 text-primary-foreground"/>
        </div>
        <span class="size-2 flex-none rounded-full" :class="statusConfig[s].dot"/>
        <span
            class="font-medium"
            :class="form.status === s ? 'text-foreground' : 'text-muted-foreground'"
        >
                    {{ statusConfig[s].label }}
                </span>
      </button>
    </CardContent>
  </Card>

  <div class="flex justify-end">
    <Button :disabled="saving" class="gap-2" @click="emit('save')">
      <Save class="size-4"/>
      {{ saving ? 'Сохраняем…' : 'Сохранить' }}
    </Button>
  </div>
</template>
