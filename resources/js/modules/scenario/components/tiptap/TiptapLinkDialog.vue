<script setup lang="ts">
import {ref, watch} from 'vue'
import {Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle} from '@/components/ui/dialog'
import {FormError, FormInput, FormToggle} from '@/components/form'
import {Button} from '@/components/ui/button'
import {Link2, Unlink} from 'lucide-vue-next'

const props = withDefaults(defineProps<{
  open: boolean
  initialUrl?: string
  initialTarget?: string | null
  hasLink?: boolean
}>(), {
  initialUrl: '',
  initialTarget: null,
  hasLink: false,
})

const emit = defineEmits<{
  'update:open': [value: boolean]
  submit: [{ href: string, target: string | null }]
  remove: []
}>()

const url = ref('')
const openInNewTab = ref(false)
const error = ref<string | null>(null)

watch(() => props.open, (val) => {
  if (!val) return
  url.value = props.initialUrl
  openInNewTab.value = props.initialTarget === '_blank'
  error.value = null
})

function close(): void {
  emit('update:open', false)
}

function submit(): void {
  const trimmed = url.value.trim()
  if (trimmed === '') {
    error.value = 'Укажите ссылку'
    return
  }
  emit('submit', {href: trimmed, target: openInNewTab.value ? '_blank' : null})
  close()
}

function remove(): void {
  emit('remove')
  close()
}
</script>

<template>
  <Dialog :open="open" @update:open="emit('update:open', $event)">
    <DialogContent class="sm:max-w-md">
      <DialogHeader>
        <DialogTitle class="flex items-center gap-2">
          <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
            <Link2 :size="14" />
          </div>
          Ссылка
        </DialogTitle>
      </DialogHeader>

      <form class="space-y-4" novalidate @submit.prevent="submit">
        <FormError :message="error" />
        <FormInput
            v-model="url"
            label="URL"
            placeholder="https://example.com"
            required
        />
        <FormToggle
            v-model="openInNewTab"
            label="Открывать в новой вкладке"
            description="Добавляет атрибут target=&quot;_blank&quot; ссылке"
        />

        <DialogFooter class="gap-2 sm:justify-between">
          <Button v-if="hasLink" type="button" variant="ghost" class="gap-1.5 text-red-600 hover:text-red-700"
                  @click="remove">
            <Unlink :size="13" />
            Убрать ссылку
          </Button>
          <div class="flex justify-end gap-2 sm:ml-auto">
            <Button type="button" variant="outline" @click="close">Отмена</Button>
            <Button type="submit">Сохранить</Button>
          </div>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>
