<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { FormError, FormInput } from '@/components/form'
import { ArrowDown, ArrowUp, Check, Pencil, Plus, Save, Trash2 } from 'lucide-vue-next'
import type { ScenarioInputField, ScenarioInputFieldType } from '@/modules/scenario/types/scenario'

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
        input_fields: ScenarioInputField[]
    }
    errors: { name?: string }
    saveError: string | null
    saving: boolean
    versionCreatedAt: string | null
    versionUpdatedAt: string | null
    statusConfig: Record<VersionStatus, StatusConfig>
    fieldTypeLabels: Record<ScenarioInputFieldType, string>
    formatDate: (iso: string | null) => string
}>()

const emit = defineEmits<{
    save: []
    openField: [idx: number | null]
    moveField: [idx: number, delta: number]
    removeField: [idx: number]
}>()

const STATUSES: VersionStatus[] = ['active', 'draft', 'archived']
</script>

<template>
    <Card class="border-border/60">
        <CardHeader class="pb-3">
            <CardTitle class="text-sm">Основное</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
            <FormError :message="saveError" />
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
                    <Check v-if="form.status === s" class="size-2.5 text-primary-foreground" />
                </div>
                <span class="size-2 flex-none rounded-full" :class="statusConfig[s].dot" />
                <span
                    class="font-medium"
                    :class="form.status === s ? 'text-foreground' : 'text-muted-foreground'"
                >
                    {{ statusConfig[s].label }}
                </span>
            </button>
        </CardContent>
    </Card>

    <Card class="border-border/60">
        <CardHeader class="pb-3 flex-row items-center justify-between">
            <div class="space-y-1">
                <CardTitle class="text-sm">Поля запуска</CardTitle>
                <p class="text-xs text-muted-foreground">
                    Параметры, которые внешняя система передаёт в <span class="font-mono">context</span> при старте сценария.
                </p>
            </div>
            <Button type="button" variant="outline" size="sm" class="gap-1.5 shrink-0" @click="emit('openField', null)">
                <Plus class="size-3.5" />
                Добавить поле
            </Button>
        </CardHeader>
        <CardContent class="p-0">
            <div
                v-if="!form.input_fields.length"
                class="px-5 py-8 text-center text-sm text-muted-foreground"
            >
                Полей пока нет.
            </div>
            <table v-else class="w-full text-sm">
                <thead>
                    <tr class="border-b text-left text-[11px] uppercase tracking-wider text-muted-foreground">
                        <th class="px-4 py-2 font-semibold">Ключ</th>
                        <th class="px-4 py-2 font-semibold">Название</th>
                        <th class="px-4 py-2 font-semibold">Тип</th>
                        <th class="w-32" />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(field, idx) in form.input_fields"
                        :key="`${idx}-${field.key}`"
                        class="border-b last:border-0"
                    >
                        <td class="px-4 py-2 font-mono text-[13px]">{{ field.key }}</td>
                        <td class="px-4 py-2">{{ field.label || '—' }}</td>
                        <td class="px-4 py-2">
                            <Badge variant="outline" class="text-xs font-normal">
                                {{ fieldTypeLabels[field.type] }}
                            </Badge>
                        </td>
                        <td class="px-2 py-2">
                            <div class="flex justify-end gap-1">
                                <Button type="button" variant="ghost" size="icon" class="size-7" :disabled="idx === 0" @click="emit('moveField', idx, -1)">
                                    <ArrowUp class="size-3.5" />
                                </Button>
                                <Button type="button" variant="ghost" size="icon" class="size-7" :disabled="idx === form.input_fields.length - 1" @click="emit('moveField', idx, 1)">
                                    <ArrowDown class="size-3.5" />
                                </Button>
                                <Button type="button" variant="ghost" size="icon" class="size-7" @click="emit('openField', idx)">
                                    <Pencil class="size-3.5" />
                                </Button>
                                <Button type="button" variant="ghost" size="icon" class="size-7 text-muted-foreground hover:text-destructive" @click="emit('removeField', idx)">
                                    <Trash2 class="size-3.5" />
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </CardContent>
    </Card>

    <div class="flex justify-end">
        <Button :disabled="saving" class="gap-2" @click="emit('save')">
            <Save class="size-4" />
            {{ saving ? 'Сохраняем…' : 'Сохранить' }}
        </Button>
    </div>
</template>
