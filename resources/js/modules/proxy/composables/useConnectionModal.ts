import {computed, ref} from 'vue'
import {proxyConnectionRepository} from '@/modules/proxy/repositories/proxyConnectionRepository'
import type {CredentialType, ProxyConnection} from '@/modules/proxy/types/connection'
import type {WebhookField} from '@/modules/proxy/types/webhook'
import {useFormToast} from '@/composables/useFormToast'
import {HttpValidationError} from '@/lib/http'

export function useConnectionModal(onSaved: () => void) {
    const open = ref(false)
    const editingId = ref<number | null>(null)
    const saving = ref(false)
    const formError = ref('')
    const errors = ref<Record<string, string>>({})

    const types = ref<CredentialType[]>([])
    const name = ref('')
    const credentialType = ref('')
    const values = ref<Record<string, unknown>>({})
    const secretFilled = ref<Record<string, boolean>>({})

    const isEditing = computed(() => editingId.value !== null)
    const currentFields = computed<WebhookField[]>(
        () => types.value.find(t => t.type === credentialType.value)?.fields ?? [],
    )

    const formToast = useFormToast({
        created: 'Доступ создан',
        updated: 'Доступ обновлён',
        deleted: 'Доступ удалён',
    })

    async function loadTypes(): Promise<void> {
        try {
            types.value = await proxyConnectionRepository.types()
        } catch {
            types.value = []
        }
    }

    void loadTypes()

    function selectType(type: string): void {
        credentialType.value = type
        if (!isEditing.value) {
            values.value = {}
            secretFilled.value = {}
        }
    }

    function openCreate(): void {
        editingId.value = null
        name.value = ''
        credentialType.value = types.value[0]?.type ?? ''
        values.value = {}
        secretFilled.value = {}
        errors.value = {}
        formError.value = ''
        open.value = true
    }

    function openEdit(connection: ProxyConnection): void {
        editingId.value = connection.id
        name.value = connection.name
        credentialType.value = connection.credential_type
        values.value = {...connection.config}
        secretFilled.value = {...connection.secret_filled}
        errors.value = {}
        formError.value = ''
        open.value = true
    }

    function close(): void {
        open.value = false
        editingId.value = null
    }

    async function save(): Promise<void> {
        errors.value = {}
        formError.value = ''
        if (!name.value.trim()) {
            errors.value.name = 'Название обязательно'
            return
        }
        saving.value = true
        try {
            const payload = {
                name: name.value.trim(),
                credential_type: credentialType.value,
                values: values.value,
            }
            if (editingId.value !== null) {
                await proxyConnectionRepository.update(editingId.value, payload)
            } else {
                await proxyConnectionRepository.create(payload)
            }
            formToast.saved(editingId.value !== null)
            close()
            onSaved()
        } catch (e: unknown) {
            if (e instanceof HttpValidationError) {
                errors.value = Object.fromEntries(
                    Object.entries(e.errors).map(([key, msgs]) => [key, msgs[0] ?? '']),
                )
                formError.value = e.message
            } else {
                formError.value = e instanceof Error ? e.message : 'Не удалось сохранить доступ.'
            }
        } finally {
            saving.value = false
        }
    }

    async function remove(connection: ProxyConnection): Promise<void> {
        try {
            await proxyConnectionRepository.destroy(connection.id)
            formToast.deleted()
            onSaved()
        } catch (e: unknown) {
            formToast.error(e, 'Не удалось удалить доступ.')
        }
    }

    return {
        open, editingId, isEditing, saving, formError, errors,
        types, name, credentialType, values, secretFilled, currentFields,
        selectType, openCreate, openEdit, close, save, remove,
    }
}
