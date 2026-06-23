import { ref } from 'vue'
import { directoryRepository } from '@/modules/directories/repositories/directoryRepository'
import type { Directory, DirectoryPayload, DirectorySchemaField } from '@/modules/directories/types/directory'
import { useFormToast } from '@/composables/useFormToast'
import { useZodForm } from '@/composables/useZodForm'
import { directorySchema } from '@/modules/directories/schemas/directorySchema'

const emptyForm = () => ({
  name: '',
  slug: '',
  description: null as string | null,
  category_ids: [] as string[],
  source_type: 'manual' as const,
  match_by: null as string | null,
  fields: [] as unknown[],
})

export function useDirectoryModal(onSaved: () => void) {
  const showModal    = ref(false)
  const editing      = ref<Directory | null>(null)
  const modalLoading = ref(false)

  const { formData: form, errors, formError, submitting, submit, reset } =
    useZodForm(directorySchema, emptyForm())

  const formToast = useFormToast({
    created: 'Справочник создан',
    updated: 'Справочник обновлён',
    deleted: 'Справочник удалён',
  })

  function openCreate(): void {
    editing.value = null
    reset(emptyForm())
    showModal.value = true
  }

  function openEdit(d: Directory): void {
    editing.value = d
    const fields = d.latest_version?.schema_json ?? d.active_version?.schema_json ?? []
    reset({
      name: d.name,
      slug: d.slug,
      description: d.description,
      category_ids: d.category_ids ?? [],
      source_type: d.source_type,
      match_by: d.match_by,
      fields,
    })
    showModal.value = true
  }

  function close(): void {
    showModal.value = false
    editing.value = null
  }

  async function save(): Promise<void> {
    const isUpdate = editing.value !== null
    try {
      await submit(async (data) => {
        const payload = data as unknown as DirectoryPayload
        if (editing.value) {
          await directoryRepository.update(editing.value.id, payload)
        } else {
          await directoryRepository.create(payload)
        }
      })
      close()
      onSaved()
      formToast.saved(isUpdate)
    } catch { /* errors уже в форме */ }
  }

  const confirmDelete  = ref<Directory | null>(null)
  const deleting       = ref(false)
  const deleteError    = ref<string | null>(null)

  function openDeleteConfirm(d: Directory): void {
    confirmDelete.value = d
    deleteError.value = null
  }

  function closeDeleteConfirm(): void {
    confirmDelete.value = null
    deleteError.value = null
  }

  async function doDelete(): Promise<void> {
    if (!confirmDelete.value) return
    deleting.value = true
    deleteError.value = null
    try {
      await directoryRepository.remove(confirmDelete.value.id)
      closeDeleteConfirm()
      onSaved()
      formToast.deleted()
    } catch (e: unknown) {
      deleteError.value = e instanceof Error ? e.message : 'Ошибка удаления.'
      formToast.error(e, 'Ошибка удаления.')
    } finally {
      deleting.value = false
    }
  }

  return {
    showModal, editing, form, errors, formError, submitting, modalLoading,
    openCreate, openEdit, close, save,
    confirmDelete, deleting, deleteError,
    openDeleteConfirm, closeDeleteConfirm, doDelete,
  }
}

export type { DirectorySchemaField }
