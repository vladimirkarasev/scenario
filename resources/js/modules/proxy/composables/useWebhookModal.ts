import {ref} from 'vue'
import {webhookRepository} from '@/modules/proxy/repositories/webhookRepository'
import type {HandlerOption, MockResponseVariant, WebhookEndpoint, WebhookField} from '@/modules/proxy/types/webhook'
import {useFormToast} from '@/composables/useFormToast'
import {useZodForm} from '@/composables/useZodForm'
import {webhookSchema, type WebhookFormValues} from '@/modules/proxy/schemas/webhookSchema'

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

function emptyForm(): WebhookFormValues {
    return {
        name: '',
        code: '',
        handler_class: '',
        method: 'POST',
        description: '',
        is_active: true,
        is_mocked: false,
        base_uri: '',
        bearer_token: '',
        config: {} as Record<string, unknown> | unknown[],
        mocks: [] as MockVariant[],
    }
}

export function useWebhookModal(onSaved: () => void) {
    const editing = ref<WebhookEndpoint | null>(null)
    const showModal = ref(false)
    const handlers = ref<HandlerOption[]>([])
    const secretFilled = ref<Record<string, boolean>>({})
    const receiveUrl = ref<string>('')

    const {formData: form, errors, formError, submitting, submit, reset} =
        useZodForm(webhookSchema, emptyForm())

    const fields = ref<WebhookField[]>([])
    const loadingFields = ref(false)

    const formToast = useFormToast({
        created: 'Интеграция создана',
        updated: 'Интеграция обновлена',
        deleted: 'Интеграция удалена',
    })

    async function loadHandlers(): Promise<void> {
        try {
            handlers.value = await webhookRepository.handlers()
        } catch {
            handlers.value = []
        }
    }

    void loadHandlers()

    async function loadFields(uuid: string): Promise<void> {
        loadingFields.value = true
        try {
            fields.value = await webhookRepository.fields(uuid)
        } catch {
            fields.value = []
        } finally {
            loadingFields.value = false
        }
    }

    function openCreate(): void {
        editing.value = null
        secretFilled.value = {}
        receiveUrl.value = ''
        reset(emptyForm())
        fields.value = []
        showModal.value = true
    }

    function openEdit(ep: WebhookEndpoint): void {
        editing.value = ep
        secretFilled.value = ep.secret_filled ?? {}
        receiveUrl.value = ep.receive_url ?? ''
        reset({
            name: ep.name,
            code: ep.code,
            handler_class: ep.handler_class,
            method: ep.method ?? 'POST',
            description: ep.description ?? '',
            is_active: ep.is_active,
            is_mocked: ep.is_mocked,
            base_uri: ep.base_uri ?? '',
            bearer_token: '',
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
        const wasEditing = editing.value !== null
        try {
            await submit(async (data) => {
                const payload = {
                    name: data.name,
                    code: data.code,
                    handler_class: data.handler_class,
                    method: data.method || 'POST',
                    description: data.description || null,
                    is_active: data.is_active,
                    is_mocked: data.is_mocked,
                    credentials: {base_uri: data.base_uri, bearer_token: data.bearer_token},
                    config: data.config,
                    mock_responses: data.mocks.map(fromMockVariant),
                }
                if (editing.value) {
                    const updated = await webhookRepository.update(editing.value.id, payload)
                    Object.assign(editing.value, updated)
                } else {
                    await webhookRepository.create(payload)
                }
            })
            close()
            onSaved()
            formToast.saved(wasEditing)
        } catch { /* errors уже в форме */
        }
    }

    async function remove(ep: WebhookEndpoint): Promise<void> {
        try {
            await webhookRepository.destroy(ep.id)
            formToast.deleted()
            onSaved()
        } catch (e: unknown) {
            formToast.error(e, 'Не удалось удалить интеграцию.')
        }
    }

    async function toggleActive(ep: WebhookEndpoint): Promise<void> {
        try {
            const updated = await webhookRepository.update(ep.id, {
                name: ep.name,
                code: ep.code,
                handler_class: ep.handler_class,
                method: ep.method ?? 'POST',
                description: ep.description,
                is_active: !ep.is_active,
                is_mocked: ep.is_mocked,
                config: ep.config,
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
        saving: submitting, editError: formError, errors, form,
        handlers, fields, loadingFields, secretFilled, receiveUrl,
        openCreate, openEdit, close, save, remove, toggleActive,
    }
}
