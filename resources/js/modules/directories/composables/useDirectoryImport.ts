import * as XLSX from 'xlsx'
import {onUnmounted, reactive, ref, watch} from 'vue'
import type {Subscription} from 'centrifuge'
import {subscribeTo, unsubscribeFrom} from '@/composables/useCentrifugo'
import {directoryRepository} from '@/modules/directories/repositories/directoryRepository'
import {sendJson} from '@/lib/http'
import type {
    Directory,
    DirectoryImport,
    DirectorySchemaField,
    DirectoryVersionSyncOptions
} from '@/modules/directories/types/directory'

interface ImportStatusEvent {
    type: 'status_update'
    import_id: number
    status: string
    processed_rows: number
    imported_rows: number
    failed_rows: number
    error_message: string | null
}

export function useDirectoryImport(directoryId: string, versionId?: number) {
    const imports = ref<DirectoryImport[]>([])
    const loading = ref(false)

    // ── Real-time subscriptions ───────────────────────────────────────────
    const activeSubs = new Map<number, Subscription>()

    function applyWsUpdate(data: ImportStatusEvent): void {
        const idx = imports.value.findIndex(i => i.id === data.import_id)
        if (idx !== -1) {
            imports.value[idx] = {
                ...imports.value[idx],
                status: data.status,
                processed_rows: data.processed_rows,
                imported_rows: data.imported_rows,
                failed_rows: data.failed_rows,
                error_message: data.error_message,
            }
        }
        if (data.status === 'completed' || data.status === 'failed') {
            activeSubs.get(data.import_id)?.unsubscribe()
            activeSubs.delete(data.import_id)
        }
    }

    function subscribeToImport(id: number): void {
        if (activeSubs.has(id)) return
        subscribeTo<ImportStatusEvent>(`directory-import:${id}`, (data) => {
            if (data.type === 'status_update') applyWsUpdate(data)
        }).then(sub => activeSubs.set(id, sub)).catch(() => {
        })
    }

    function subscribeToActiveImports(): void {
        imports.value
            .filter(i => i.status === 'pending' || i.status === 'processing')
            .forEach(i => subscribeToImport(i.id))
    }

    function destroySubs(): void {
        activeSubs.forEach(sub => {
            void unsubscribeFrom(sub)
        })
        activeSubs.clear()
    }

    onUnmounted(() => destroySubs())

    // ── Load ──────────────────────────────────────────────────────────────

    async function loadImports(): Promise<void> {
        loading.value = true
        try {
            const result = await directoryRepository.imports(directoryId, versionId)
            imports.value = result.items
            subscribeToActiveImports()
        } finally {
            loading.value = false
        }
    }

    // ── Import options persistence ─────────────────────────────────────────

    // ── Sync (API source) ────────────────────────────────────────────────

    const syncModalOpen = ref(false)
    const syncRunning = ref(false)
    const syncResult = ref<{ added: number; updated: number; deleted: number } | null>(null)
    const syncOptions = reactive<DirectoryVersionSyncOptions>({
        add_new: true,
        update_existing: true,
        delete_unused: false
    })

    function openSync(): void {
        syncResult.value = null
        syncRunning.value = false
        syncModalOpen.value = true
    }

    async function runSync(): Promise<void> {
        syncRunning.value = true
        syncResult.value = null
        try {
            await directoryRepository.syncApi(directoryId, {...syncOptions})
            syncResult.value = {added: 0, updated: 0, deleted: 0}
            await loadImports()
        } catch { /* shown via syncResult staying null */
        } finally {
            syncRunning.value = false
        }
    }

    // ── Excel import wizard ───────────────────────────────────────────────

    const importStep = ref<1 | 2>(1)
    const importFile = ref<File | null>(null)
    const importOptions = reactive({addNew: true, updateExisting: true, deleteUnused: false})

    function restoreImportOptions(settings: Directory['import_settings']): void {
        if (typeof settings.add_new === 'boolean') {
            importOptions.addNew = settings.add_new
            syncOptions.add_new = settings.add_new
        }
        if (typeof settings.update_existing === 'boolean') {
            importOptions.updateExisting = settings.update_existing
            syncOptions.update_existing = settings.update_existing
        }
        if (typeof settings.delete_unused === 'boolean') {
            importOptions.deleteUnused = settings.delete_unused
            syncOptions.delete_unused = settings.delete_unused
        }
    }

    let saveTimer: ReturnType<typeof setTimeout> | null = null

    function scheduleSettingsSave(body: Record<string, boolean>): void {
        if (saveTimer) clearTimeout(saveTimer)
        saveTimer = setTimeout(async () => {
            try {
                await sendJson(`/api/directories/${directoryId}/import-settings`, {
                    method: 'PATCH',
                    fallbackMessage: 'Failed to save import settings.',
                    body,
                })
            } catch { /* silent */
            }
        }, 400)
    }

    watch(() => ({...importOptions}), () => {
        scheduleSettingsSave({
            add_new: importOptions.addNew,
            update_existing: importOptions.updateExisting,
            delete_unused: importOptions.deleteUnused,
        })
    })

    watch(() => ({...syncOptions}), () => {
        scheduleSettingsSave({
            add_new: syncOptions.add_new,
            update_existing: syncOptions.update_existing,
            delete_unused: syncOptions.delete_unused,
        })
    })
    const importLoading = ref(false)
    const importError = ref('')
    const parsedHeaders = ref<{ key: string; label: string }[]>([])
    const parsedPreviewRows = ref<string[][]>([])
    const importMapping = reactive<Record<string, string>>({})
    const storedSchemaFields = ref<DirectorySchemaField[]>([])

    function onFileSelect(e: Event): void {
        importFile.value = (e.target as HTMLInputElement).files?.[0] ?? null
    }

    async function goToMapping(schemaFields: DirectorySchemaField[]): Promise<void> {
        if (!importOptions.addNew && !importOptions.updateExisting && !importOptions.deleteUnused) {
            importError.value = 'Выберите хотя бы один режим.'
            return
        }
        if (!importFile.value) {
            importError.value = 'Выберите файл.'
            return
        }
        importError.value = ''
        try {
            const buf = await importFile.value.arrayBuffer()
            const wb = XLSX.read(buf, {type: 'array'})
            const ws = wb.Sheets[wb.SheetNames[0]]
            const rows = XLSX.utils.sheet_to_json<string[]>(ws, {header: 1})
            const headerRow = (rows[0] ?? []).map(String)
            parsedHeaders.value = headerRow.map(h => ({key: h, label: h}))
            parsedPreviewRows.value = rows.slice(1, 4).map(r => headerRow.map((_, i) => String((r as string[])[i] ?? '')))
            storedSchemaFields.value = schemaFields
            schemaFields.forEach(f => {
                importMapping[f.key] = ''
            })
        } catch {
            importError.value = 'Не удалось прочитать файл. Убедитесь, что это Excel или CSV.'
            return
        }
        importStep.value = 2
    }

    // Returns true on success so the component can switch to history
    async function submitImport(): Promise<boolean> {
        const hasMappings = Object.values(importMapping).some(v => v !== '')
        if (!hasMappings) {
            importError.value = 'Сопоставьте хотя бы одну колонку с полем.'
            return false
        }

        importLoading.value = true
        importError.value = ''
        try {
            const fd = new FormData()
            fd.append('file', importFile.value!)
            fd.append('source_type', 'file')
            fd.append('mode', importOptions.deleteUnused ? 'replace' : importOptions.updateExisting ? 'update' : 'create')
            fd.append('add_new', importOptions.addNew ? '1' : '0')
            fd.append('update_existing', importOptions.updateExisting ? '1' : '0')
            fd.append('delete_unused', importOptions.deleteUnused ? '1' : '0')
            if (versionId != null) fd.append('version_id', String(versionId))

            storedSchemaFields.value.forEach((field, idx) => {
                fd.append(`columns[${idx}][key]`, field.key)
                fd.append(`columns[${idx}][name]`, field.name)
                fd.append(`columns[${idx}][type]`, field.type)
                fd.append(`columns[${idx}][nullable]`, field.nullable ? '1' : '0')
                fd.append(`columns[${idx}][sort_order]`, String(field.sort_order ?? 0))
            })

            Object.entries(importMapping).forEach(([schemaFieldKey, excelColumnKey]) => {
                if (excelColumnKey) fd.append(`mapping[${excelColumnKey}]`, schemaFieldKey)
            })

            const result = await directoryRepository.importExcel(directoryId, fd)
            imports.value = [result.item, ...imports.value]
            resetImport()
            return true
        } catch (e: unknown) {
            importError.value = e instanceof Error ? e.message : 'Ошибка импорта.'
            return false
        } finally {
            importLoading.value = false
        }
    }

    function resetImport(): void {
        importStep.value = 1
        importFile.value = null
        importError.value = ''
        importLoading.value = false
        parsedHeaders.value = []
        parsedPreviewRows.value = []
        storedSchemaFields.value = []
    }

    function importLabel(status: string): string {
        return ({
            pending: 'Ожидает',
            processing: 'Импортируем',
            completed: 'Завершён',
            failed: 'Ошибка'
        } as Record<string, string>)[status] ?? status
    }

    function importVariant(status: string): 'default' | 'destructive' | 'secondary' {
        if (status === 'completed') return 'default'
        if (status === 'failed') return 'destructive'
        return 'secondary'
    }

    return {
        imports, loading, loadImports, restoreImportOptions,
        syncModalOpen, syncRunning, syncResult, syncOptions, openSync, runSync,
        importStep, importFile, importOptions, importLoading, importError,
        parsedHeaders, parsedPreviewRows, importMapping,
        onFileSelect, goToMapping, submitImport, resetImport,
        importLabel, importVariant,
    }
}
