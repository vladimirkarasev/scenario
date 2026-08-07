import {computed, onUnmounted, reactive, ref, watch} from 'vue'
import type {Subscription} from 'centrifuge'
import {subscribeTo, unsubscribeFrom} from '@/composables/useCentrifugo'
import {directoryRepository} from '@/modules/directories/repositories/directoryRepository'
import {
    SPREADSHEET_PREVIEW_ROWS,
    spreadsheetColumnsError,
    spreadsheetFileError,
} from '@/modules/directories/lib/spreadsheetImport'
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
    source_type: string
    sources_total: number
}

export function useDirectoryImport(directoryId: string, versionId?: number) {
    const imports = ref<DirectoryImport[]>([])
    const loading = ref(false)

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

    function destroy(): void {
        activeSubs.forEach(sub => {
            void unsubscribeFrom(sub)
        })
        activeSubs.clear()
        if (saveTimer) clearTimeout(saveTimer)
        if (pendingSettings) void flushSettingsSave()
    }

    onUnmounted(destroy)

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

    const syncModalOpen = ref(false)
    const syncRunning = ref(false)
    const syncResult = ref<{ added: number; updated: number; deleted: number } | null>(null)
    function openSync(): void {
        syncResult.value = null
        syncRunning.value = false
        syncModalOpen.value = true
    }

    async function runSync(options?: DirectoryVersionSyncOptions): Promise<void> {
        syncRunning.value = true
        syncResult.value = null
        try {
            await directoryRepository.syncApi(directoryId, options)
            syncResult.value = {added: 0, updated: 0, deleted: 0}
            await loadImports()
        } catch { /* shown via syncResult staying null */
        } finally {
            syncRunning.value = false
        }
    }

    const importStep = ref<1 | 2>(1)
    const importFiles = ref<File[]>([])
    const importFile = computed<File | null>({
        get: () => importFiles.value[0] ?? null,
        set: file => {
            importFiles.value = file ? [file] : []
        },
    })
    const importOptions = reactive({addNew: true, updateExisting: true, deleteUnused: false})

    function restoreImportOptions(settings: Directory['import_settings']): void {
        if (typeof settings.add_new === 'boolean') {
            importOptions.addNew = settings.add_new
        }
        if (typeof settings.update_existing === 'boolean') {
            importOptions.updateExisting = settings.update_existing
        }
        if (typeof settings.delete_unused === 'boolean') {
            importOptions.deleteUnused = settings.delete_unused
        }
    }

    let saveTimer: ReturnType<typeof setTimeout> | null = null
    let pendingSettings: Record<string, boolean> | null = null
    let settingsSaveRunning = false

    async function flushSettingsSave(): Promise<void> {
        if (settingsSaveRunning) return
        settingsSaveRunning = true
        try {
            while (pendingSettings) {
                const settings = pendingSettings
                pendingSettings = null
                try {
                    await directoryRepository.updateImportSettings(directoryId, settings)
                } catch {
                    continue
                }
            }
        } finally {
            settingsSaveRunning = false
        }
    }

    function scheduleSettingsSave(body: Record<string, boolean>): void {
        pendingSettings = body
        if (saveTimer) clearTimeout(saveTimer)
        saveTimer = setTimeout(() => void flushSettingsSave(), 400)
    }

    watch(() => ({...importOptions}), () => {
        scheduleSettingsSave({
            add_new: importOptions.addNew,
            update_existing: importOptions.updateExisting,
            delete_unused: importOptions.deleteUnused,
        })
    })

    const importLoading = ref(false)
    const importError = ref('')
    const parsedHeaders = ref<{ key: string; label: string }[]>([])
    const parsedPreviewRows = ref<string[][]>([])
    const importMapping = reactive<Record<string, string>>({})
    const storedSchemaFields = ref<DirectorySchemaField[]>([])

    function onFileSelect(e: Event): void {
        importFiles.value = Array.from((e.target as HTMLInputElement).files ?? [])
    }

    async function goToMapping(schemaFields: DirectorySchemaField[]): Promise<void> {
        if (!importOptions.addNew && !importOptions.updateExisting && !importOptions.deleteUnused) {
            importError.value = 'Выберите хотя бы один режим.'
            return
        }
        if (importFiles.value.length === 0) {
            importError.value = 'Выберите хотя бы один файл.'
            return
        }
        const invalidFileMessage = importFiles.value.map(spreadsheetFileError).find(message => message !== null)
        if (invalidFileMessage) {
            importError.value = invalidFileMessage
            return
        }
        importError.value = ''
        try {
            const XLSX = await import('xlsx')
            const parsedFiles = await Promise.all(importFiles.value.map(async file => {
                const buf = await file.arrayBuffer()
                const wb = XLSX.read(buf, {type: 'array', sheetRows: SPREADSHEET_PREVIEW_ROWS})
                const ws = wb.Sheets[wb.SheetNames[0]]
                if (!ws) throw new Error(`В файле «${file.name}» нет листов.`)
                const rows = XLSX.utils.sheet_to_json<string[]>(ws, {header: 1})

                return {
                    file,
                    rows,
                    headers: (rows[0] ?? []).map(String),
                }
            }))
            const headerRow = parsedFiles[0]?.headers ?? []
            const columnsError = spreadsheetColumnsError(headerRow.length)
            if (columnsError) {
                importError.value = columnsError
                return
            }
            const headerSignature = JSON.stringify(headerRow)
            const incompatibleFile = parsedFiles.find(file => JSON.stringify(file.headers) !== headerSignature)

            if (incompatibleFile) {
                importError.value = `Колонки файла «${incompatibleFile.file.name}» отличаются от первого файла.`
                return
            }

            parsedHeaders.value = headerRow.map(h => ({key: h, label: h}))
            parsedPreviewRows.value = (parsedFiles[0]?.rows ?? [])
                .slice(1, 4)
                .map(r => headerRow.map((_, i) => String((r as string[])[i] ?? '')))
            storedSchemaFields.value = schemaFields

            const normalize = (s: string): string => s.trim().toLowerCase()
            const headerKeyByLabel = new Map<string, string>()
            parsedHeaders.value.forEach(h => {
                const key = normalize(h.label)
                if (!headerKeyByLabel.has(key)) headerKeyByLabel.set(key, h.key)
            })
            schemaFields.forEach(f => {
                importMapping[f.key] = headerKeyByLabel.get(normalize(f.name))
                    ?? headerKeyByLabel.get(normalize(f.key))
                    ?? ''
            })
        } catch {
            importError.value = 'Не удалось прочитать файл. Убедитесь, что это Excel или CSV.'
            return
        }
        importStep.value = 2
    }

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
            importFiles.value.forEach(file => fd.append('files[]', file))
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
        importFiles.value = []
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
        syncModalOpen, syncRunning, syncResult, openSync, runSync,
        importStep, importFile, importFiles, importOptions, importLoading, importError,
        parsedHeaders, parsedPreviewRows, importMapping,
        onFileSelect, goToMapping, submitImport, resetImport,
        importLabel, importVariant,
    }
}
