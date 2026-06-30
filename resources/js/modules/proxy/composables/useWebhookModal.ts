import {computed, ref} from 'vue'
import {webhookRepository} from '@/modules/proxy/repositories/webhookRepository'
import {proxyConnectionRepository} from '@/modules/proxy/repositories/proxyConnectionRepository'
import type {HandlerOption, MockResponseVariant, WebhookEndpoint, WebhookField} from '@/modules/proxy/types/webhook'
import type {ProxyConnection} from '@/modules/proxy/types/connection'
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
        is_active: v.is_active ?? false,
    }
}

function fromMockVariant(v: MockVariant): MockResponseVariant {
    return {
        name: v.name,
        status: v.status,
        body: v.body,
        headers: v.headers as Record<string, string> | null,
        is_active: v.is_active,
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
        category_ids: [] as string[],
        connection_id: null as number | null,
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
    const connections = ref<ProxyConnection[]>([])

    const {formData: form, errors, formError, submitting, submit, reset} =
        useZodForm(webhookSchema, emptyForm())

    const fields = ref<WebhookField[]>([])
    const loadingFields = ref(false)

    // Тип доступа, который требует выбранный обработчик (null — доступ не нужен).
    const requiredCredentialType = computed<string | null>(
        () => handlers.value.find(h => h.class === form.handler_class)?.credential_type ?? null,
    )
    // Доступы, подходящие выбранному обработчику.
    const availableConnections = computed<ProxyConnection[]>(() =>
        requiredCredentialType.value === null
            ? []
            : connections.value.filter(c => c.credential_type === requiredCredentialType.value),
    )

    const formToast = useFormToast({
        created: 'Интеграция создана',
        updated: 'Интеграция обновлена',
        deleted: 'Интеграция удалена',
    })

    async function loadHandlers(): Promise<void> {
        try {
            const acc: HandlerOption[] = []
            let page = 1
            for (; ;) {
                const qs = new URLSearchParams({'page[number]': String(page), 'page[size]': '100'})
                const {items, meta} = await webhookRepository.handlers(qs)
                acc.push(...items)
                if (page >= meta.last_page || items.length === 0) break
                page++
            }
            handlers.value = acc
        } catch {
            handlers.value = []
        }
    }

    void loadHandlers()

    async function loadConnections(): Promise<void> {
        try {
            connections.value = await proxyConnectionRepository.list()
        } catch {
            connections.value = []
        }
    }

    void loadConnections()

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

    function openCreate(categoryId: string | null = null): void {
        editing.value = null
        secretFilled.value = {}
        receiveUrl.value = ''
        reset({...emptyForm(), category_ids: categoryId ? [categoryId] : []})
        fields.value = []
        showModal.value = true
        void loadConnections()
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
            category_ids: ep.category_ids ?? [],
            connection_id: ep.connection_id ?? null,
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
                    category_ids: data.category_ids,
                    connection_id: data.connection_id,
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
                connection_id: ep.connection_id,
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
        requiredCredentialType, availableConnections, loadConnections,
        openCreate, openEdit, close, save, remove, toggleActive,
    }
}
