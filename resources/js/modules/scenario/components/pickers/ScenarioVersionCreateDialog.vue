<script setup lang="ts">
import {ref, watch} from 'vue'
import {router} from '@inertiajs/vue3'
import {toast} from 'vue-sonner'
import {Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogFooter} from '@/components/ui/dialog'
import {Button} from '@/components/ui/button'
import {Label} from '@/components/ui/label'
import {Input} from '@/components/ui/input'
import {scenarioVersionRepository} from '@/modules/scenario/repositories/scenarioVersionRepository'

const props = defineProps<{
  open: boolean
  scenarioId: string
}>()

const emit = defineEmits<{
  'update:open': [v: boolean]
}>()

const name = ref('')
const error = ref<string | null>(null)
const submitting = ref(false)

watch(() => props.open, (o) => {
  if (o) {
    name.value = ''
    error.value = null
    submitting.value = false
  }
})

async function submit() {
  if (submitting.value) return
  const trimmed = name.value.trim()
  if (!trimmed) {
    error.value = 'Название обязательно'
    return
  }
  submitting.value = true
  error.value = null
  try {
    const version = await scenarioVersionRepository.create(props.scenarioId, {
      name: trimmed,
      status: 'draft',
      schema_json: {format: 'scenario-flow', version: 1, viewport: {x: 0, y: 0, zoom: 1}, blocks: [], connections: []},
    })
    emit('update:open', false)
    toast.success('Версия создана')
    router.visit(route('scenario-versions.edit', version.id))
  } catch (e: unknown) {
    const msg = e instanceof Error ? e.message : 'Не удалось создать версию.'
    error.value = msg
    toast.error(msg)
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <Dialog :open="open" @update:open="(v: boolean) => emit('update:open', v)">
    <DialogContent class="sm:max-w-sm">
      <DialogHeader>
        <DialogTitle>Новая версия сценария</DialogTitle>
        <DialogDescription class="sr-only">Создание новой версии сценария</DialogDescription>
      </DialogHeader>

      <form class="space-y-3" @submit.prevent="submit">
        <div class="space-y-1.5">
          <Label for="version-name">Название <span class="text-red-500">*</span></Label>
          <Input
              id="version-name"
              v-model="name"
              placeholder="v2"
              autofocus
              required
              :class="error ? 'border-red-300' : ''"
              @keydown.enter.prevent="submit"
              @input="error = null"
          />
          <p v-if="error" class="text-[12px] text-red-600">{{ error }}</p>
        </div>
      </form>

      <DialogFooter>
        <Button type="button" variant="outline" @click="emit('update:open', false)">Отмена</Button>
        <Button type="button" :disabled="submitting" @click="submit">
          {{ submitting ? '…' : 'Создать' }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
