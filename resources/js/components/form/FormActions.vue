<script setup lang="ts">
import { Loader2 } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'

withDefaults(defineProps<{
    submitting?: boolean
    disabled?: boolean
    submitLabel?: string
    cancelLabel?: string
}>(), {
    submitting: false,
    disabled: false,
    submitLabel: 'Сохранить',
    cancelLabel: 'Отмена',
})

defineEmits<{
    submit: []
    cancel: []
}>()
</script>

<template>
    <div class="flex items-center justify-end gap-2 border-t border-slate-100 bg-slate-50/60 px-6 py-3">
        <slot name="extra" />
        <Button variant="outline" type="button" :disabled="submitting" @click="$emit('cancel')">
            {{ cancelLabel }}
        </Button>
        <Button type="submit" :disabled="submitting || disabled" @click="$emit('submit')">
            <Loader2 v-if="submitting" class="mr-1.5 size-4 animate-spin" />
            {{ submitLabel }}
        </Button>
    </div>
</template>
