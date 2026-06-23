import {computed, reactive, ref} from 'vue'
import {defineStore} from 'pinia'
import {destroyJson, getJson, sendJson} from '@/lib/http'

interface Action {
    id: number
    name: string
    key: string
    description?: string | null
    type: string
    is_active: boolean
    config?: Record<string, unknown>
    schema?: Record<string, unknown>
    ui_schema?: Record<string, unknown>
    runs?: unknown[]
}

interface ActionCredential {
    id: number
    name: string
    type: string
    config?: Record<string, unknown>
}

const DEFAULT_ACTION_CONFIG = {
    url: 'https://api.example.com/webhook',
    method: 'POST',
    credential_id: null,
    headers: {'X-Source': 'admin-panel'},
    query: {},
    body_type: 'json',
    body: {user_id: '{{ user.id }}', email: '{{ user.email }}'},
    timeout: 15,
    retry_count: 0,
}

const DEFAULT_ACTION_SCHEMA = {
    type: 'object',
    properties: {user: {type: 'object'}},
}

export const useActionManagerStore = defineStore('actionManager', () => {
    const actions = ref<Action[]>([])
    const credentials = ref<ActionCredential[]>([])
    const runs = ref<unknown[]>([])
    const loading = ref(false)
    const error = ref('')
    const actionDialogOpen = ref(false)
    const credentialDialogOpen = ref(false)
    const executeDialogOpen = ref(false)
    const editingActionId = ref<number | null>(null)
    const editingCredentialId = ref<number | null>(null)
    const executingActionId = ref<number | null>(null)

    const actionForm = reactive({
        name: '',
        key: '',
        description: '',
        type: 'http_request',
        is_active: true,
        configText: JSON.stringify(DEFAULT_ACTION_CONFIG, null, 2),
        schemaText: JSON.stringify(DEFAULT_ACTION_SCHEMA, null, 2),
        uiSchemaText: JSON.stringify({}, null, 2),
    })

    const credentialForm = reactive({
        name: '',
        type: 'none',
        configText: JSON.stringify({}, null, 2),
        secretsText: JSON.stringify({}, null, 2),
    })

    const executionForm = reactive({
        inputText: JSON.stringify({user: {id: 42, email: 'user@example.com'}}, null, 2),
    })

    const actionDialogTitle = computed(() => editingActionId.value ? 'Edit action' : 'Create action')
    const credentialDialogTitle = computed(() => editingCredentialId.value ? 'Edit credential' : 'Create credential')

    async function load(): Promise<void> {
        loading.value = true
        error.value = ''

        try {
            const [actionsPayload, credentialsPayload, runsPayload] = await Promise.all([
                getJson<Record<string, unknown>>('/api/actions', 'Failed to load actions.'),
                getJson<Record<string, unknown>>('/api/action-credentials', 'Failed to load credentials.'),
                getJson<Record<string, unknown>>('/api/action-runs', 'Failed to load action runs.'),
            ])

            actions.value = actionsPayload.items as Action[] ?? []
            credentials.value = credentialsPayload.items as ActionCredential[] ?? []
            runs.value = runsPayload.items as unknown[] ?? []
        } catch (e: unknown) {
            error.value = e instanceof Error ? e.message : String(e)
        } finally {
            loading.value = false
        }
    }

    function resetActionForm(): void {
        actionForm.name = ''
        actionForm.key = ''
        actionForm.description = ''
        actionForm.type = 'http_request'
        actionForm.is_active = true
        actionForm.configText = JSON.stringify(DEFAULT_ACTION_CONFIG, null, 2)
        actionForm.schemaText = JSON.stringify(DEFAULT_ACTION_SCHEMA, null, 2)
        actionForm.uiSchemaText = JSON.stringify({}, null, 2)
        editingActionId.value = null
    }

    function resetCredentialForm(): void {
        credentialForm.name = ''
        credentialForm.type = 'none'
        credentialForm.configText = JSON.stringify({}, null, 2)
        credentialForm.secretsText = JSON.stringify({}, null, 2)
        editingCredentialId.value = null
    }

    function openCreateAction(): void {
        resetActionForm()
        error.value = ''
        actionDialogOpen.value = true
    }

    function openEditAction(item: Action): void {
        editingActionId.value = item.id
        actionForm.name = item.name
        actionForm.key = item.key
        actionForm.description = item.description ?? ''
        actionForm.type = item.type
        actionForm.is_active = Boolean(item.is_active)
        actionForm.configText = JSON.stringify(item.config ?? {}, null, 2)
        actionForm.schemaText = JSON.stringify(item.schema ?? {}, null, 2)
        actionForm.uiSchemaText = JSON.stringify(item.ui_schema ?? {}, null, 2)
        error.value = ''
        actionDialogOpen.value = true
    }

    function openCreateCredential(): void {
        resetCredentialForm()
        error.value = ''
        credentialDialogOpen.value = true
    }

    function openEditCredential(item: ActionCredential): void {
        editingCredentialId.value = item.id
        credentialForm.name = item.name
        credentialForm.type = item.type
        credentialForm.configText = JSON.stringify(item.config ?? {}, null, 2)
        credentialForm.secretsText = JSON.stringify({}, null, 2)
        error.value = ''
        credentialDialogOpen.value = true
    }

    function openExecuteDialog(item: Action): void {
        executingActionId.value = item.id
        executionForm.inputText = JSON.stringify({
            user: {id: 42, email: 'user@example.com'},
            action_key: item.key,
        }, null, 2)
        error.value = ''
        executeDialogOpen.value = true
    }

    function parseJson(text: string, fieldName: string): unknown {
        try {
            return text.trim() === '' ? {} : JSON.parse(text)
        } catch {
            throw new Error(`${fieldName} must be valid JSON.`)
        }
    }

    async function submitAction(): Promise<void> {
        loading.value = true
        error.value = ''

        try {
            const payload = await sendJson<Record<string, unknown>>(
                editingActionId.value ? `/api/actions/${editingActionId.value}` : '/api/actions',
                {
                    method: editingActionId.value ? 'PUT' : 'POST',
                    body: {
                        name: actionForm.name,
                        key: actionForm.key,
                        description: actionForm.description || null,
                        type: actionForm.type,
                        is_active: actionForm.is_active,
                        config: parseJson(actionForm.configText, 'Action config'),
                        schema: parseJson(actionForm.schemaText, 'Action schema'),
                        ui_schema: parseJson(actionForm.uiSchemaText, 'Action ui_schema'),
                    },
                    fallbackMessage: 'Failed to save action.',
                },
            )

            const item = payload.item as Action
            actions.value = editingActionId.value
                ? actions.value.map((current) => current.id === item.id ? item : current)
                : [...actions.value, item].sort((left, right) => left.name.localeCompare(right.name))

            actionDialogOpen.value = false
            resetActionForm()
        } catch (e: unknown) {
            error.value = e instanceof Error ? e.message : String(e)
        } finally {
            loading.value = false
        }
    }

    async function submitCredential(): Promise<void> {
        loading.value = true
        error.value = ''

        try {
            const payload = await sendJson<Record<string, unknown>>(
                editingCredentialId.value ? `/api/action-credentials/${editingCredentialId.value}` : '/api/action-credentials',
                {
                    method: editingCredentialId.value ? 'PUT' : 'POST',
                    body: {
                        name: credentialForm.name,
                        type: credentialForm.type,
                        config: parseJson(credentialForm.configText, 'Credential config'),
                        secrets: parseJson(credentialForm.secretsText, 'Credential secrets'),
                    },
                    fallbackMessage: 'Failed to save credential.',
                },
            )

            const item = payload.item as ActionCredential
            credentials.value = editingCredentialId.value
                ? credentials.value.map((current) => current.id === item.id ? item : current)
                : [...credentials.value, item].sort((left, right) => left.name.localeCompare(right.name))

            credentialDialogOpen.value = false
            resetCredentialForm()
        } catch (e: unknown) {
            error.value = e instanceof Error ? e.message : String(e)
        } finally {
            loading.value = false
        }
    }

    async function removeAction(item: Action): Promise<void> {
        if (!window.confirm(`Delete action "${item.name}"?`)) {
            return
        }

        loading.value = true
        error.value = ''

        try {
            await destroyJson(`/api/actions/${item.id}`, 'Failed to delete action.')
            actions.value = actions.value.filter((current) => current.id !== item.id)
            runs.value = (runs.value as Array<{ action_id: number }>).filter((current) => current.action_id !== item.id)
        } catch (e: unknown) {
            error.value = e instanceof Error ? e.message : String(e)
        } finally {
            loading.value = false
        }
    }

    async function removeCredential(item: ActionCredential): Promise<void> {
        if (!window.confirm(`Delete credential "${item.name}"?`)) {
            return
        }

        loading.value = true
        error.value = ''

        try {
            await destroyJson(`/api/action-credentials/${item.id}`, 'Failed to delete credential.')
            credentials.value = credentials.value.filter((current) => current.id !== item.id)
        } catch (e: unknown) {
            error.value = e instanceof Error ? e.message : String(e)
        } finally {
            loading.value = false
        }
    }

    async function executeAction(): Promise<void> {
        if (!executingActionId.value) {
            return
        }

        loading.value = true
        error.value = ''

        try {
            const payload = await sendJson<Record<string, unknown>>(`/api/actions/${executingActionId.value}/execute`, {
                body: {input: parseJson(executionForm.inputText, 'Execution input')},
                fallbackMessage: 'Failed to execute action.',
            })

            const action = actions.value.find((item) => item.id === executingActionId.value)
            if (action) {
                action.runs = payload.runs as unknown[] ?? []
            }

            await load()
            executeDialogOpen.value = false
        } catch (e: unknown) {
            error.value = e instanceof Error ? e.message : String(e)
        } finally {
            loading.value = false
        }
    }

    return {
        actions,
        credentials,
        runs,
        loading,
        error,
        actionDialogOpen,
        credentialDialogOpen,
        executeDialogOpen,
        actionDialogTitle,
        credentialDialogTitle,
        actionForm,
        credentialForm,
        executionForm,
        load,
        openCreateAction,
        openEditAction,
        openCreateCredential,
        openEditCredential,
        openExecuteDialog,
        submitAction,
        submitCredential,
        removeAction,
        removeCredential,
        executeAction,
    }
})
