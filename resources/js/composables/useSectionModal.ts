import {computed, ref, type ComputedRef, type Ref} from 'vue'
import type {z} from 'zod'
import {useFormToast} from '@/composables/useFormToast'
import {useZodForm} from '@/composables/useZodForm'
import type {SectionCategory} from '@/types/section'

export interface SectionFormBase {
    name: string
    parent_id: string | null
}

export interface SectionModalApi {
    open: Ref<boolean>
    editingId: Ref<string | null>
    isEditing: ComputedRef<boolean>
    form: SectionFormBase
    errors: Record<string, string>
    formError: Ref<string | null>
    submitting: Ref<boolean>
    close: () => void
    submit: () => void | Promise<void>
}

export interface SectionDefaultPayload {
    name: string
    parent_id: string | null
    is_active: boolean
}

export interface SectionModalConfig<
    T extends SectionCategory,
    Schema extends z.ZodType<SectionFormBase>,
    P = SectionDefaultPayload,
> {
    schema: Schema
    defaults: z.infer<Schema>
    create: (payload: P) => Promise<T>
    update: (id: string, payload: P) => Promise<T>
    buildPayload?: (data: z.infer<Schema>) => P
    fromCategory?: (category: T) => Partial<z.infer<Schema>>
    onOpenModal?: () => void
    onOpenEdit?: (category: T) => void
}

export function useSectionModal<
    T extends SectionCategory,
    Schema extends z.ZodType<SectionFormBase>,
    P = SectionDefaultPayload,
>(
    config: SectionModalConfig<T, Schema, P>,
    onCreated: (category: T) => void,
    onUpdated: (category: T) => void,
) {
    const open = ref(false)
    const editingId = ref<string | null>(null)

    const {formData, errors, formError, submitting, submit, reset} =
        useZodForm(config.schema, config.defaults)

    const isEditing = computed(() => editingId.value !== null)

    const formToast = useFormToast({
        created: 'Раздел создан',
        updated: 'Раздел обновлён',
    })

    const buildPayload = config.buildPayload
        ?? ((data: z.infer<Schema>) => ({
            name: data.name.trim(),
            parent_id: data.parent_id,
            is_active: true,
        }) as P)

    const fromCategory = config.fromCategory
        ?? ((category: T) => ({name: category.name, parent_id: category.parent_id} as Partial<z.infer<Schema>>))

    function openModal(parentId: string | null = null): void {
        editingId.value = null
        config.onOpenModal?.()
        reset({...config.defaults, parent_id: parentId})
        open.value = true
    }

    function openEdit(category: T): void {
        editingId.value = category.id
        config.onOpenEdit?.(category)
        reset({...config.defaults, ...fromCategory(category)})
        open.value = true
    }

    function close(): void {
        open.value = false
        editingId.value = null
    }

    async function submitForm(): Promise<void> {
        try {
            await submit(async (data) => {
                const payload = buildPayload(data)
                if (editingId.value) {
                    onUpdated(await config.update(editingId.value, payload))
                    formToast.saved(true)
                } else {
                    onCreated(await config.create(payload))
                    formToast.saved(false)
                }
            })
            close()
        } catch { /* ошибка отображена в форме */
        }
    }

    return {
        open,
        editingId,
        isEditing,
        form: formData,
        errors,
        formError,
        submitting,
        openModal,
        openEdit,
        close,
        submit: submitForm,
    }
}
