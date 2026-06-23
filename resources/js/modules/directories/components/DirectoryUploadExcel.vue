<script setup lang="ts">
import { useDirectoryImport } from '@/modules/directories/composables/useDirectoryImport'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { ArrowLeft, FileSpreadsheet, Import, Loader2, Upload, X } from 'lucide-vue-next'
import type { DirectorySchemaField } from '@/modules/directories/types/directory'
import { ref, watch } from 'vue'

const props = defineProps<{
    directoryId: string
    versionId: number
    schemaFields: DirectorySchemaField[]
    importOptions: { addNew: boolean; updateExisting: boolean; deleteUnused: boolean }
}>()
const emit = defineEmits<{ importStarted: [] }>()

const imp = useDirectoryImport(props.directoryId, props.versionId)
watch(() => props.importOptions, opts => { Object.assign(imp.importOptions, opts) }, { immediate: true, deep: true })

const ACCEPTED = ['.xlsx', '.xls', '.csv', '.ods']
const ACCEPTED_MIME = [
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-excel',
    'text/csv',
    'application/vnd.oasis.opendocument.spreadsheet',
]

const isDragging   = ref(false)
const fileInputRef = ref<HTMLInputElement | null>(null)

function openFilePicker(): void { fileInputRef.value?.click() }

function handleNativeSelect(e: Event): void {
    const file = (e.target as HTMLInputElement).files?.[0] ?? null
    if (file) imp.importFile.value = file
}

function handleDragOver(e: DragEvent): void { e.preventDefault(); isDragging.value = true }

function handleDragLeave(e: DragEvent): void {
    if (!(e.currentTarget as HTMLElement).contains(e.relatedTarget as Node | null)) isDragging.value = false
}

function handleDrop(e: DragEvent): void {
    e.preventDefault()
    isDragging.value = false
    const file = e.dataTransfer?.files[0]
    if (!file) return
    if (!ACCEPTED_MIME.includes(file.type) && !ACCEPTED.some(ext => file.name.toLowerCase().endsWith(ext))) {
        imp.importError.value = `Неподдерживаемый формат. Используйте: ${ACCEPTED.join(', ')}`
        return
    }
    imp.importError.value = ''
    imp.importFile.value  = file
}

function clearFile(): void {
    imp.importFile.value  = null
    imp.importError.value = ''
    if (fileInputRef.value) fileInputRef.value.value = ''
}

function formatSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`
    if (bytes < 1_048_576) return `${(bytes / 1024).toFixed(1)} KB`
    return `${(bytes / 1_048_576).toFixed(1)} MB`
}

async function handleSubmit(): Promise<void> {
    const ok = await imp.submitImport()
    if (ok) emit('importStarted')
}
</script>

<template>
    <!-- Step 1: file selection -->
    <Card v-if="imp.importStep.value === 1" class="border-border/60">
        <CardHeader>
            <CardTitle>Импорт данных</CardTitle>
            <CardDescription>Загрузите файл — колонки будут прочитаны автоматически.</CardDescription>
        </CardHeader>
        <CardContent class="grid gap-5">
            <input ref="fileInputRef" type="file" :accept="ACCEPTED.join(',')" class="sr-only" @change="handleNativeSelect" />

            <div
                v-if="!imp.importFile.value"
                role="button" tabindex="0"
                class="relative flex flex-col items-center justify-center gap-4 rounded-2xl border-2 border-dashed px-6 py-12 text-center transition-all duration-150 cursor-pointer select-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                :class="isDragging ? 'border-primary bg-primary/5 scale-[1.01]' : 'border-border/60 bg-muted/10 hover:border-primary/50 hover:bg-muted/20'"
                @click="openFilePicker" @keydown.enter.prevent="openFilePicker" @keydown.space.prevent="openFilePicker"
                @dragover="handleDragOver" @dragleave="handleDragLeave" @drop="handleDrop"
            >
                <div class="flex size-16 items-center justify-center rounded-2xl transition-colors duration-150" :class="isDragging ? 'bg-primary/15' : 'bg-muted/40'">
                    <Upload class="size-7 transition-colors duration-150" :class="isDragging ? 'text-primary' : 'text-muted-foreground'" />
                </div>
                <div class="space-y-1">
                    <p class="text-sm font-medium" :class="isDragging ? 'text-primary' : 'text-foreground'">
                        {{ isDragging ? 'Отпустите для загрузки' : 'Перетащите файл сюда' }}
                    </p>
                    <p class="text-xs text-muted-foreground">или <span class="text-primary underline-offset-2 hover:underline">нажмите для выбора</span></p>
                </div>
                <div class="flex flex-wrap justify-center gap-1.5">
                    <span v-for="ext in ACCEPTED" :key="ext" class="rounded-md border border-border/60 bg-background px-2 py-0.5 font-mono text-[10px] text-muted-foreground">{{ ext }}</span>
                </div>
            </div>

            <div v-else class="flex items-center gap-4 rounded-2xl border border-primary/30 bg-primary/5 px-5 py-4 transition-all">
                <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                    <FileSpreadsheet class="size-5 text-primary" />
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-foreground">{{ imp.importFile.value.name }}</p>
                    <p class="text-xs text-muted-foreground">{{ formatSize(imp.importFile.value.size) }}</p>
                </div>
                <button type="button" class="flex size-7 shrink-0 items-center justify-center rounded-lg text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive" @click="clearFile">
                    <X class="size-4" />
                </button>
            </div>

            <div v-if="imp.importError.value" class="rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                {{ imp.importError.value }}
            </div>
            <Button :disabled="!imp.importFile.value" class="gap-2 self-start" @click="imp.goToMapping(schemaFields)">
                <Upload class="size-4" /> Читать колонки
            </Button>
        </CardContent>
    </Card>

    <!-- Step 2: column mapping -->
    <Card v-else-if="imp.importStep.value === 2" class="border-border/60">
        <CardHeader class="flex flex-row items-start justify-between gap-4">
            <div class="space-y-1">
                <CardTitle>Сопоставление колонок</CardTitle>
                <CardDescription>Обнаружено {{ imp.parsedHeaders.value.length }} колонок в файле.</CardDescription>
            </div>
            <Button variant="outline" size="sm" class="shrink-0 gap-2" @click="imp.importStep.value = 1">
                <ArrowLeft class="size-4" /> Назад
            </Button>
        </CardHeader>
        <CardContent class="grid gap-6">
            <div v-if="imp.parsedHeaders.value.length === 0" class="rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-500">
                Заголовки не найдены в первой строке файла.
            </div>
            <div class="overflow-hidden rounded-xl border border-border/60">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Поле справочника</TableHead>
                            <TableHead>Колонка в источнике</TableHead>
                            <TableHead v-if="imp.parsedPreviewRows.value.length > 0" class="text-muted-foreground text-xs">Пример данных</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="f in schemaFields" :key="f.key">
                            <TableCell>
                                <div class="font-medium">{{ f.name }}</div>
                                <div class="font-mono text-xs text-muted-foreground">{{ f.key }}</div>
                            </TableCell>
                            <TableCell>
                                <select v-model="imp.importMapping[f.key]" class="flex h-9 w-full rounded-lg border border-input bg-background px-3 text-sm">
                                    <option value="">— Не сопоставлять —</option>
                                    <option v-for="h in imp.parsedHeaders.value" :key="h.key" :value="h.key">{{ h.label }}</option>
                                </select>
                            </TableCell>
                            <TableCell v-if="imp.parsedPreviewRows.value.length > 0">
                                <template v-if="imp.importMapping[f.key]">
                                    <div v-for="(row, ri) in imp.parsedPreviewRows.value" :key="ri" class="truncate text-xs text-muted-foreground max-w-[180px]" :class="{ 'font-medium text-foreground': ri === 0 }">
                                        {{ row[imp.parsedHeaders.value.findIndex(h => h.key === imp.importMapping[f.key])] || '—' }}
                                    </div>
                                </template>
                                <span v-else class="text-xs text-muted-foreground/50">—</span>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
            <div v-if="imp.importError.value" class="rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                {{ imp.importError.value }}
            </div>
            <div class="flex gap-3">
                <Button :disabled="imp.importLoading.value" class="gap-2" @click="handleSubmit">
                    <Loader2 v-if="imp.importLoading.value" class="size-4 animate-spin" />
                    <Import v-else class="size-4" />
                    {{ imp.importLoading.value ? 'Запускаем...' : 'Запустить импорт' }}
                </Button>
                <Button variant="outline" @click="imp.resetImport()">Отмена</Button>
            </div>
        </CardContent>
    </Card>
</template>
