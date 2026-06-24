import {reactive, ref} from 'vue'
import {actionRepository} from '@/modules/actions/repositories/actionRepository'
import type {
    Action,
    ActionConfigField,
    ActionInputField,
    ActionType,
    ActionTypeMeta,
    InputFieldType
} from '@/modules/actions/types/action'
import {useFormToast} from '@/composables/useFormToast'
import {useZodForm} from '@/composables/useZodForm'
import {actionSchema} from '@/modules/actions/schemas/actionSchema'
import {toSlug} from '@/lib/slug'

function defaultByType(type: string): unknown {
    if (type === 'boolean') return false
    if (type === 'number') return null
    if (type === 'key_value') return {}
    return ''
}

function buildConfig(fields: ActionConfigField[]): Record<string, unknown> {
    const result: Record<string, unknown> = {}
    for (const f of fields) result[f.key] = f.default ?? defaultByType(f.type)
    return result
}

export function useActionModal(
    actionTypes: () => ActionTypeMeta[],
    onSaved: (action: Action) => void,
) {
    const show = ref(false)
    const editingId = ref<string | null>(null)
    // Пока slug не правили вручную, при создании он автоматически слугифицируется из названия.
    const slugEdited = ref(false)

    const formToast = useFormToast({
        created: 'Action создан',
        updated: 'Action обновлён',
        deleted: 'Action удалён',
    })

    const {formData: form, errors, formError, submitting, submit, reset} =
        useZodForm(actionSchema, {
            name: '', slug: '', code: '', description: '',
            type: 'template_file' as ActionType,
            is_active: true,
            config: {} as Record<string, unknown>,
            input_fields: [] as ActionInputField[],
            category_ids: [] as string[],
        })

    function fieldsFor(type: ActionType): ActionConfigField[] {
        return actionTypes().find(m => m.value === type)?.fields ?? []
    }

    function defaultCodeFor(type: ActionType): string {
        return actionTypes().find(m => m.value === type)?.default_code ?? ''
    }

    // Название меняет slug только при создании и пока slug не редактировали вручную.
    function onNameInput(value: string): void {
        form.name = value
        if (editingId.value === null && !slugEdited.value) {
            form.slug = toSlug(value)
        }
    }

    function onSlugInput(value: string): void {
        form.slug = value
        slugEdited.value = true
    }

    function openCreate(presetCategoryId: string | null = null): void {
        editingId.value = null
        slugEdited.value = false
        const initialType: ActionType = actionTypes()[0]?.value ?? 'template_file'
        reset({
            name: '', slug: '', code: defaultCodeFor(initialType),
            description: '', type: initialType, is_active: true,
            config: buildConfig(fieldsFor(initialType)),
            input_fields: [],
            category_ids: presetCategoryId ? [presetCategoryId] : [],
        })
        show.value = true
    }

    function openEdit(action: Action): void {
        editingId.value = action.id
        slugEdited.value = true
        reset({
            name: action.name,
            slug: action.slug,
            code: action.code,
            description: action.description ?? '',
            type: action.type,
            is_active: action.is_active,
            config: {...buildConfig(fieldsFor(action.type)), ...(action.config ?? {})},
            input_fields: action.input_fields.map(f => ({...f})),
            category_ids: [...(action.category_ids ?? [])],
        })
        show.value = true
    }

    function removeInputField(idx: number): void {
        form.input_fields.splice(idx, 1)
    }

    function moveInputField(idx: number, delta: number): void {
        const target = idx + delta
        if (target < 0 || target >= form.input_fields.length) return
        const [item] = form.input_fields.splice(idx, 1)
        form.input_fields.splice(target, 0, item)
    }

    const inputFieldTypes: { value: InputFieldType, label: string }[] = [
        {value: 'string', label: 'Строка'},
        {value: 'number', label: 'Число'},
        {value: 'boolean', label: 'Boolean'},
        {value: 'uuid', label: 'UUID'},
        {value: 'email', label: 'Email'},
    ]

    const fieldModalOpen = ref(false)
    const fieldModalIdx = ref<number | null>(null)
    const fieldModalDraft = reactive<ActionInputField>({
        key: '', label: '', type: 'string', required: false, default: null,
    })
    const fieldModalError = ref<string | null>(null)

    function openFieldModal(idx: number | null = null): void {
        fieldModalIdx.value = idx
        fieldModalError.value = null
        if (idx === null) {
            fieldModalDraft.key = ''
            fieldModalDraft.label = ''
            fieldModalDraft.type = 'string'
            fieldModalDraft.required = false
            fieldModalDraft.default = null
        } else {
            const f = form.input_fields[idx]
            fieldModalDraft.key = f.key
            fieldModalDraft.label = f.label
            fieldModalDraft.type = f.type
            fieldModalDraft.required = f.required
            fieldModalDraft.default = f.default
        }
        fieldModalOpen.value = true
    }

    function saveFieldModal(): void {
        const key = fieldModalDraft.key.trim()
        if (!key) {
            fieldModalError.value = 'Key обязателен'
            return
        }
        if (!/^[a-z][a-z0-9_]*$/.test(key)) {
            fieldModalError.value = 'Только латиница, цифры и _, первый символ — буква'
            return
        }
        const next: ActionInputField = {
            key,
            label: fieldModalDraft.label.trim() || key,
            type: fieldModalDraft.type,
            required: fieldModalDraft.required,
            default: fieldModalDraft.default,
        }
        if (fieldModalIdx.value === null) {
            form.input_fields.push(next)
        } else {
            form.input_fields[fieldModalIdx.value] = next
        }
        fieldModalOpen.value = false
    }

    function onTypeChange(): void {
        form.code = defaultCodeFor(form.type as ActionType)
        const next = {...buildConfig(fieldsFor(form.type as ActionType)), ...form.config}
        for (const key of Object.keys(next)) {
            if (!fieldsFor(form.type as ActionType).some(f => f.key === key)) delete next[key]
        }
        form.config = next
    }

    function close(): void {
        show.value = false
        editingId.value = null
    }

    async function save(): Promise<void> {
        const isUpdate = editingId.value !== null
        const id = editingId.value
        try {
            await submit(async (data) => {
                const payload = {
                    name: data.name,
                    slug: data.slug,
                    code: data.code,
                    description: data.description || null,
                    type: data.type as ActionType,
                    is_active: data.is_active,
                    config: data.config,
                    schema: {},
                    ui_schema: {},
                    input_fields: data.input_fields
                        .filter(f => f.key)
                        .map(f => ({...f, default: f.default ?? null})) as ActionInputField[],
                    category_ids: data.category_ids,
                }
                const result = id
                    ? await actionRepository.update(id, payload)
                    : await actionRepository.create(payload)
                onSaved(result)
            })
            close()
            formToast.saved(isUpdate)
        } catch { /* errors уже в форме */
        }
    }

    async function toggleActive(action: Action): Promise<Action | null> {
        const next: Action = {...action, is_active: !action.is_active}
        try {
            const result = await actionRepository.update(action.id, {
                name: next.name,
                slug: next.slug,
                code: next.code,
                description: next.description,
                type: next.type,
                is_active: next.is_active,
                config: next.config ?? {},
                schema: next.schema ?? {},
                ui_schema: next.ui_schema ?? {},
                input_fields: next.input_fields,
                category_ids: next.category_ids,
            })
            formToast.saved(true)
            return result
        } catch (e: unknown) {
            formError.value = e instanceof Error ? e.message : String(e)
            formToast.error(e, 'Ошибка обновления.')
            return null
        }
    }

    async function remove(action: Action): Promise<boolean> {
        if (!window.confirm(`Удалить action "${action.name}"?`)) return false
        try {
            await actionRepository.remove(action.id)
            formToast.deleted()
            return true
        } catch (e: unknown) {
            formError.value = e instanceof Error ? e.message : String(e)
            formToast.error(e, 'Ошибка удаления.')
            return false
        }
    }

    return {
        show, saving: submitting, error: formError, errors, editingId, form,
        openCreate, openEdit, onTypeChange, onNameInput, onSlugInput, close, save,
        toggleActive, remove,
        fieldsFor,
        removeInputField, moveInputField,
        inputFieldTypes,
        fieldModalOpen, fieldModalIdx, fieldModalDraft, fieldModalError,
        openFieldModal, saveFieldModal,
    }
}
