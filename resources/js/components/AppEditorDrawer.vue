<script setup lang="ts">
import { computed, useSlots } from 'vue'
import { X, Loader2 } from 'lucide-vue-next'
import { Drawer, DrawerContent, DrawerTitle, DrawerDescription } from '@/components/ui/drawer'
import { cn } from '@/lib/utils'

const props = withDefaults(defineProps<{
    open: boolean
    title: string
    subtitle?: string
    description?: string
    direction?: 'left' | 'right' | 'top' | 'bottom'
    widthClass?: string
    iconBgClass?: string
    cancelLabel?: string
    saveLabel?: string
    saving?: boolean
    canSave?: boolean
    showFooter?: boolean
    dismissible?: boolean
    modal?: boolean
    lockOutside?: boolean
}>(), {
    direction: 'right',
    widthClass: '!w-[680px] !max-w-[95vw]',
    iconBgClass: 'bg-blue-100',
    cancelLabel: 'Отмена',
    saveLabel: 'Сохранить',
    saving: false,
    canSave: true,
    showFooter: true,
    dismissible: true,
    modal: true,
    lockOutside: false,
})

const emit = defineEmits<{
    'update:open': [value: boolean]
    save: []
    cancel: []
}>()

const slots = useSlots()
const hasFooterSlot = computed(() => Boolean(slots.footer))
const renderFooter = computed(() => props.showFooter || hasFooterSlot.value)

function close(): void {
    emit('update:open', false)
}

function onCancel(): void {
    emit('cancel')
    close()
}

function onSave(): void {
    if (props.saving || !props.canSave) return
    emit('save')
}
</script>

<template>
    <Drawer :open="open" :direction="direction" :dismissible="dismissible" :modal="modal" @update:open="emit('update:open', $event)">
        <DrawerContent
            :class="cn('flex flex-col p-0', widthClass)"
            @pointer-down-outside="lockOutside ? $event.preventDefault() : undefined"
            @focus-outside="lockOutside ? $event.preventDefault() : undefined"
        >
            <DrawerTitle class="sr-only">{{ title }}</DrawerTitle>
            <DrawerDescription class="sr-only">{{ description ?? title }}</DrawerDescription>

            <!-- Header -->
            <div class="flex h-14 shrink-0 items-center justify-between border-b border-slate-200 px-5">
                <div class="flex min-w-0 items-center gap-2.5">
                    <div v-if="slots.icon" :class="cn('flex h-7 w-7 shrink-0 items-center justify-center rounded-lg', iconBgClass)">
                        <slot name="icon" />
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 text-[14px] font-semibold text-slate-900">
                            <span class="truncate">{{ title }}</span>
                            <slot name="title-badge" />
                        </div>
                        <div v-if="subtitle" class="truncate text-[11px] text-slate-500">{{ subtitle }}</div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <slot name="header-extra" />
                    <button type="button" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" @click="close">
                        <X class="size-4" />
                    </button>
                </div>
            </div>

            <!-- Body -->
            <div class="flex min-h-0 flex-1 overflow-hidden">
                <slot />
            </div>

            <!-- Footer -->
            <div v-if="renderFooter" class="flex shrink-0 items-center justify-end gap-2 border-t border-slate-200 px-5 py-3">
                <slot name="footer">
                    <button
                        type="button"
                        class="rounded-xl px-4 py-2 text-[13px] font-medium text-slate-600 transition hover:bg-slate-100"
                        @click="onCancel"
                    >
                        {{ cancelLabel }}
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-9 items-center gap-2 rounded-xl bg-blue-600 px-4 text-[13px] font-medium text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="saving || !canSave"
                        @click="onSave"
                    >
                        <Loader2 v-if="saving" :size="14" class="animate-spin" />
                        {{ saving ? 'Сохраняем…' : saveLabel }}
                    </button>
                </slot>
            </div>
        </DrawerContent>
    </Drawer>
</template>
