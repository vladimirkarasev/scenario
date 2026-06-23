import { ref } from 'vue'
import { webhookRepository } from '@/modules/proxy/repositories/webhookRepository'
import type { MockResponseVariant, WebhookEndpoint, WebhookField } from '@/modules/proxy/types/webhook'
import { useFormToast } from '@/composables/useFormToast'
import { useZodForm } from '@/composables/useZodForm'
import { webhookSchema, type WebhookFormValues } from '@/modules/proxy/schemas/webhookSchema'

type MockVariant = WebhookFormValues['mocks'][number]

function toMockVariant(v: MockResponseVariant): MockVariant {
  return {
    name: v.name ?? null,
    status: v.status ?? 200,
    body: (v.body ?? {}) as Record<string, unknown> | unknown[],
    headers: v.headers ? (v.headers as Record<string, unknown>) : null,
    match: v.match ?? null,
  }
}

function fromMockVariant(v: MockVariant): MockResponseVariant {
  return {
    name: v.name,
    status: v.status,
    body: v.body,
    headers: v.headers as Record<string, string> | null,
    match: v.match,
  }
}

export function useWebhookModal(onSaved: () => void) {
  const editing      = ref<WebhookEndpoint | null>(null)
  const showModal    = ref(false)

  const { formData: form, errors, formError, submitting, submit, reset } =
    useZodForm(webhookSchema, {
      name: '',
      description: '',
      is_active: true,
      is_mocked: false,
      config: {} as Record<string, unknown> | unknown[],
      mocks: [] as MockVariant[],
    })

  const fields        = ref<WebhookField[]>([])
  const loadingFields = ref(false)

  const formToast = useFormToast({
    created: 'Webhook создан',
    updated: 'Webhook обновлён',
  })

  async function loadFields(webhookUuid: string): Promise<void> {
    loadingFields.value = true
    try {
      fields.value = await webhookRepository.fields(webhookUuid)
    } catch {
      fields.value = []
    } finally {
      loadingFields.value = false
    }
  }

  function openEdit(ep: WebhookEndpoint): void {
    editing.value = ep
    reset({
      name: ep.name,
      description: ep.description ?? '',
      is_active: ep.is_active,
      is_mocked: ep.is_mocked,
      config: (ep.config as Record<string, unknown> | unknown[]) ?? {},
      mocks: (ep.mock_responses ?? []).map(toMockVariant),
    })
    fields.value = []
    showModal.value = true
    loadFields(ep.uuid)
  }

  function close(): void {
    showModal.value = false
    editing.value = null
  }

  async function save(): Promise<void> {
    if (!editing.value) return
    const id = editing.value.id
    const code = editing.value.code
    const handlerClass = editing.value.handler_class
    try {
      await submit(async (data) => {
        const updated = await webhookRepository.update(id, {
          name: data.name,
          code,
          description: data.description || null,
          is_active: data.is_active,
          is_mocked: data.is_mocked,
          handler_class: handlerClass,
          config: data.config,
          mock_responses: data.mocks.map(fromMockVariant),
        })
        if (editing.value) Object.assign(editing.value, updated)
      })
      close()
      onSaved()
      formToast.saved(true)
    } catch { /* errors уже в форме */ }
  }

  async function toggleActive(ep: WebhookEndpoint): Promise<void> {
    try {
      const updated = await webhookRepository.update(ep.id, {
        name:           ep.name,
        code:           ep.code,
        description:    ep.description,
        is_active:      !ep.is_active,
        is_mocked:      ep.is_mocked,
        handler_class:  ep.handler_class,
        config:         ep.config,
        mock_responses: ep.mock_responses ?? [],
      })
      Object.assign(ep, updated)
      formToast.saved(true)
    } catch (e: unknown) {
      formToast.error(e, 'Ошибка обновления.')
    }
  }

  return {
    editing, showModal,
    saving: submitting, editError: formError, errors,
    form, fields, loadingFields,
    openEdit, close, save, toggleActive,
  }
}
