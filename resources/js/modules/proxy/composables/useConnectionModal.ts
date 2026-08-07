import {computed, ref, toRef} from 'vue'
import {proxyConnectionRepository} from '@/modules/proxy/repositories/proxyConnectionRepository'
import type {CredentialType, ProxyConnection} from '@/modules/proxy/types/connection'
import type {WebhookField} from '@/modules/proxy/types/webhook'
import {useFormToast} from '@/composables/useFormToast'
import {useZodForm} from '@/composables/useZodForm'
import {connectionSchema} from '@/modules/proxy/schemas/connectionSchema'

export function useConnectionModal(onSaved: () => void) {
    const open = ref(false)
    const editingId = ref<number | null>(null)
    const types = ref<CredentialType[]>([])
    const secretFilled = ref<Record<string, boolean>>({})
    const {formData: form, errors, formError, submitting: saving, submit, reset} = useZodForm(connectionSchema, {
        name: '',
        credential_type: '',
        values: {},
    })
    const name = toRef(form, 'name')
    const credentialType = toRef(form, 'credential_type')
    const values = toRef(form, 'values')

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
        reset({name: '', credential_type: types.value[0]?.type ?? '', values: {}})
        secretFilled.value = {}
        open.value = true
    }

    function openEdit(connection: ProxyConnection): void {
        editingId.value = connection.id
        reset({
            name: connection.name,
            credential_type: connection.credential_type,
            values: {...connection.config},
        })
        secretFilled.value = {...connection.secret_filled}
        open.value = true
    }

    function close(): void {
        open.value = false
        editingId.value = null
    }

    async function save(): Promise<void> {
        try {
            const isUpdate = editingId.value !== null
            await submit(async (payload) => {
                if (editingId.value !== null) {
                    await proxyConnectionRepository.update(editingId.value, payload)
                } else {
                    await proxyConnectionRepository.create(payload)
                }
            })
            formToast.saved(isUpdate)
            close()
            onSaved()
        } catch {
            return
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
