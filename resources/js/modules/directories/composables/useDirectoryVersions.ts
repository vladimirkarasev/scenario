import { ref } from 'vue'
import { toast } from 'vue-sonner'
import { directoryRepository } from '@/modules/directories/repositories/directoryRepository'
import { toSlug } from '@/lib/slug'
import { defaultOperator } from '@/modules/directories/types/directory'
import type { DirectorySchemaField, DirectoryVersion, DirectoryVersionSyncOptions, DirectoryOtherOptionSettings, FilterDisplayType, SourceType } from '@/modules/directories/types/directory'

function emptyField(): DirectorySchemaField {
  return { key: '', name: '', type: 'string', filter_type: 'string', filter_multiple: false, nullable: true, filterable: false, searchable: false, filter_operator: 'contains', filter_placeholder: null, default: null, sort_order: 0, rules: [], options: [] }
}

export function useDirectoryVersions(directoryId: string) {
  const versions = ref<DirectoryVersion[]>([])
  const loading  = ref(false)

  async function loadVersions(): Promise<void> {
    loading.value = true
    try {
      const result = await directoryRepository.versions(directoryId)
      versions.value = result.items
    } finally {
      loading.value = false
    }
  }

  // ── Create ───────────────────────────────────────────────────────────

  const createDialogOpen  = ref(false)
  const createClone       = ref(false)
  const createLoading     = ref(false)
  const createError       = ref<string | null>(null)

  async function createVersion(flash: (msg: string) => void): Promise<void> {
    createLoading.value = true
    createError.value   = null
    try {
      const result = await directoryRepository.createVersion(directoryId, { clone: createClone.value })
      versions.value = [result.item, ...versions.value]
      createDialogOpen.value = false
      flash(`Версия v${result.item.version_number} создана.`)
    } catch (e: unknown) {
      createError.value = e instanceof Error ? e.message : 'Ошибка создания версии.'
    } finally {
      createLoading.value = false
    }
  }

  // ── Activate ─────────────────────────────────────────────────────────

  const activateDialogOpen = ref(false)
  const activatingVersion  = ref<DirectoryVersion | null>(null)
  const activateLoading    = ref(false)

  async function confirmActivate(flash: (msg: string) => void): Promise<void> {
    if (!activatingVersion.value) return
    activateLoading.value = true
    try {
      await directoryRepository.activateVersion(directoryId, activatingVersion.value.id)
      versions.value = versions.value.map(v => ({ ...v, is_active: v.id === activatingVersion.value!.id }))
      flash(`Версия v${activatingVersion.value.version_number} активирована.`)
      activateDialogOpen.value = false
      activatingVersion.value  = null
    } finally {
      activateLoading.value = false
    }
  }

  // ── Delete ────────────────────────────────────────────────────────────

  const deleteDialogOpen = ref(false)
  const deletingVersion  = ref<DirectoryVersion | null>(null)
  const deleteLoading    = ref(false)
  const deleteError      = ref<string | null>(null)

  async function confirmDelete(): Promise<void> {
    if (!deletingVersion.value) return
    deleteLoading.value = true
    deleteError.value   = null
    try {
      await directoryRepository.removeVersion(directoryId, deletingVersion.value.id)
      versions.value = versions.value.filter(v => v.id !== deletingVersion.value!.id)
      deleteDialogOpen.value = false
      deletingVersion.value  = null
    } catch (e: unknown) {
      deleteError.value = e instanceof Error ? e.message : 'Ошибка удаления.'
    } finally {
      deleteLoading.value = false
    }
  }

  // ── Schema editing ────────────────────────────────────────────────────

  const editableSchema      = ref<DirectorySchemaField[]>([])
  const editableMatchBy     = ref<string>('')
  const editableDefaultSort = ref<string>('')
  const schemaSaving = ref(false)
  const manualKeyEdited = new WeakSet<object>()

  function syncSchemaFrom(version: DirectoryVersion | null, matchBy?: string | null, defaultSort?: string | null): void {
    const fields = version?.schema_json ?? []
    editableSchema.value = fields.length
      ? fields.map(f => ({
          ...f,
          filter_type:        (f.filter_type ?? f.type) as FilterDisplayType,
          filter_multiple:    f.filter_multiple    ?? false,
          filter_operator:    f.filter_operator    ?? defaultOperator(f.filter_type ?? f.type),
          filter_placeholder: f.filter_placeholder ?? null,
        }))
      : [emptyField()]
    if (matchBy !== undefined) editableMatchBy.value = matchBy ?? ''
    if (defaultSort !== undefined) editableDefaultSort.value = defaultSort ?? ''
  }

  function addSchemaField(): void {
    editableSchema.value.push(emptyField())
  }

  function removeSchemaField(index: number): void {
    if (editableSchema.value.length > 1) editableSchema.value.splice(index, 1)
  }

  // ── Field modal ───────────────────────────────────────────────────────

  const fieldModalOpen  = ref(false)
  const fieldModalIndex = ref<number>(-1)
  const fieldModalDraft = ref<DirectorySchemaField>(emptyField())

  function openFieldModal(index: number): void {
    fieldModalIndex.value = index
    fieldModalDraft.value = { ...editableSchema.value[index] }
    fieldModalOpen.value  = true
  }

  function onFieldModalNameInput(): void {
    if (!manualKeyEdited.has(fieldModalDraft.value)) {
      fieldModalDraft.value.key = toSlug(fieldModalDraft.value.name)
    }
  }

  function onFieldModalKeyInput(): void {
    manualKeyEdited.add(fieldModalDraft.value)
  }

  function onFieldModalTypeChange(): void {
    fieldModalDraft.value.filter_type     = fieldModalDraft.value.type as FilterDisplayType
    fieldModalDraft.value.filter_operator = defaultOperator(fieldModalDraft.value.type)
  }

  function onFieldModalFilterTypeChange(): void {
    fieldModalDraft.value.filter_multiple = false
    fieldModalDraft.value.filter_operator = defaultOperator(fieldModalDraft.value.filter_type)
  }

  const fieldModalNewOption = ref('')

  function addFieldModalOption(): void {
    const val = fieldModalNewOption.value.trim()
    if (!val) return
    const opts = fieldModalDraft.value.options ?? []
    if (!opts.includes(val)) {
      fieldModalDraft.value = { ...fieldModalDraft.value, options: [...opts, val] }
    }
    fieldModalNewOption.value = ''
  }

  function removeFieldModalOption(index: number): void {
    const opts = [...(fieldModalDraft.value.options ?? [])]
    opts.splice(index, 1)
    fieldModalDraft.value = { ...fieldModalDraft.value, options: opts }
  }

  function saveFieldModal(): void {
    if (fieldModalIndex.value >= 0) {
      editableSchema.value[fieldModalIndex.value] = { ...fieldModalDraft.value }
    }
    fieldModalOpen.value = false
  }

  // legacy inline helpers kept for compatibility
  function onSchemaNameInput(field: DirectorySchemaField): void {
    if (!manualKeyEdited.has(field)) field.key = toSlug(field.name)
  }
  function onSchemaKeyInput(field: DirectorySchemaField): void {
    manualKeyEdited.add(field)
  }

  const settingsSaving = ref(false)
  const settingsError  = ref<string | null>(null)

  async function saveVersionSettings(versionId: number, sourceType: SourceType, syncOptions?: DirectoryVersionSyncOptions, otherSettings?: DirectoryOtherOptionSettings): Promise<void> {
    settingsSaving.value = true
    settingsError.value  = null
    try {
      const result = await directoryRepository.updateVersionSettings(directoryId, versionId, sourceType, syncOptions, otherSettings)
      versions.value = versions.value.map(v =>
        v.id === versionId
          ? {
              ...v,
              source_type: result.item.source_type,
              sync_options: result.item.sync_options,
              allow_other: result.item.allow_other,
              other_label: result.item.other_label,
              other_external_key: result.item.other_external_key,
            }
          : v,
      )
    } catch (e: unknown) {
      settingsError.value = e instanceof Error ? e.message : 'Ошибка сохранения настроек.'
    } finally {
      settingsSaving.value = false
    }
  }

  async function saveSchema(versionId: number): Promise<void> {
    schemaSaving.value = true
    try {
      const fields = editableSchema.value.filter(f => f.key)
      const result = await directoryRepository.updateVersionSchema(directoryId, versionId, fields, editableMatchBy.value || null, editableDefaultSort.value || null)
      versions.value = versions.value.map(v =>
        v.id === versionId ? { ...v, schema_json: result.item.schema_json } : v,
      )
      toast.success('Схема сохранена.')
    } catch (e: unknown) {
      toast.error(e instanceof Error ? e.message : 'Ошибка сохранения схемы.')
    } finally {
      schemaSaving.value = false
    }
  }

  // ── Code editing ──────────────────────────────────────────────────────

  const editingCodeId    = ref<number | null>(null)
  const editingCodeValue = ref('')
  const codeSaving       = ref(false)

  function startEditCode(version: DirectoryVersion): void {
    editingCodeId.value    = version.id
    editingCodeValue.value = version.code ?? ''
  }

  function cancelEditCode(): void {
    editingCodeId.value    = null
    editingCodeValue.value = ''
  }

  async function saveCode(versionId: number): Promise<void> {
    codeSaving.value = true
    try {
      const result = await directoryRepository.updateVersionCode(directoryId, versionId, editingCodeValue.value.trim() || null)
      versions.value = versions.value.map(v => v.id === versionId ? { ...v, code: result.item.code } : v)
      editingCodeId.value = null
    } catch (e: unknown) {
      toast.error(e instanceof Error ? e.message : 'Ошибка сохранения кода версии.')
    } finally {
      codeSaving.value = false
    }
  }

  return {
    versions, loading, loadVersions,
    createDialogOpen, createClone, createLoading, createError, createVersion,
    activateDialogOpen, activatingVersion, activateLoading, confirmActivate,
    deleteDialogOpen, deletingVersion, deleteLoading, deleteError, confirmDelete,
    settingsSaving, settingsError, saveVersionSettings,
    editableSchema, editableMatchBy, editableDefaultSort, schemaSaving,
    syncSchemaFrom, onSchemaNameInput, onSchemaKeyInput, addSchemaField, removeSchemaField, saveSchema,
    fieldModalOpen, fieldModalIndex, fieldModalDraft, fieldModalNewOption,
    openFieldModal, onFieldModalNameInput, onFieldModalKeyInput, onFieldModalTypeChange, onFieldModalFilterTypeChange,
    addFieldModalOption, removeFieldModalOption, saveFieldModal,
    editingCodeId, editingCodeValue, codeSaving, startEditCode, cancelEditCode, saveCode,
  }
}

export type DirectoryVersionsContext = ReturnType<typeof useDirectoryVersions>
