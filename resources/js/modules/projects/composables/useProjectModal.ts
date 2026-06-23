import { ref } from 'vue'
import { projectRepository } from '@/modules/projects/repositories/projectRepository'
import type { Project } from '@/modules/projects/types/project'
import { useFormToast } from '@/composables/useFormToast'
import { useZodForm } from '@/composables/useZodForm'
import { projectSchema, type ProjectFormValues } from '@/modules/projects/schemas/projectSchema'

const randomSecret = () => crypto.randomUUID().replace(/-/g, '')

const initialForm = (): ProjectFormValues => ({
  name: '',
  sitekey: '',
  host: '',
  shared_secret: '',
  is_active: true,
})

export function useProjectModal(onSaved: () => void) {
  const showModal    = ref(false)
  const editing      = ref<Project | null>(null)
  const modalLoading = ref(false)

  const { formData: form, errors, formError, submitting, submit, reset } =
    useZodForm(projectSchema, initialForm())

  const formToast = useFormToast({
    created: 'Проект создан',
    updated: 'Проект обновлён',
    deleted: 'Проект удалён',
  })

  function openCreate(): void {
    editing.value = null
    reset({ name: '', sitekey: randomSecret(), host: '', shared_secret: randomSecret(), is_active: true })
    showModal.value = true
  }

  function regenerateSecret(): void {
    form.shared_secret = randomSecret()
  }

  async function openEdit(p: Project): Promise<void> {
    editing.value = p
    reset()
    showModal.value = true
    modalLoading.value = true
    try {
      const fresh = await projectRepository.find(p.id)
      editing.value = fresh
      reset({
        name: fresh.name,
        sitekey: fresh.sitekey,
        host: fresh.host,
        shared_secret: fresh.shared_secret ?? '',
        is_active: fresh.is_active,
      })
    } catch (e: unknown) {
      formError.value = e instanceof Error ? e.message : 'Не удалось загрузить проект.'
    } finally {
      modalLoading.value = false
    }
  }

  function close(): void {
    showModal.value = false
    editing.value = null
  }

  async function save(): Promise<void> {
    const isUpdate = editing.value !== null
    try {
      await submit(async (data) => {
        if (editing.value) {
          await projectRepository.update(editing.value.id, { ...data })
        } else {
          await projectRepository.create({ ...data })
        }
      })
      close()
      onSaved()
      formToast.saved(isUpdate)
    } catch { /* ошибка уже в formError/errors */ }
  }

  async function toggleActive(p: Project): Promise<void> {
    try {
      await projectRepository.update(p.id, {
        name: p.name,
        sitekey: p.sitekey,
        host: p.host,
        shared_secret: p.shared_secret ?? '',
        is_active: !p.is_active,
      })
      p.is_active = !p.is_active
      formToast.saved(true)
    } catch (e: unknown) {
      formToast.error(e, 'Ошибка обновления.')
    }
  }

  // ── Delete confirm ───────────────────────────────────────────────────

  const confirmDelete = ref<Project | null>(null)
  const deleting      = ref(false)
  const deleteError   = ref<string | null>(null)

  function openDeleteConfirm(p: Project): void {
    confirmDelete.value = p
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
      await projectRepository.remove(confirmDelete.value.id)
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
    showModal, editing, form, formError, errors, submitting, modalLoading,
    openCreate, openEdit, close, save, toggleActive, regenerateSecret,
    confirmDelete, deleting, deleteError,
    openDeleteConfirm, closeDeleteConfirm, doDelete,
  }
}
