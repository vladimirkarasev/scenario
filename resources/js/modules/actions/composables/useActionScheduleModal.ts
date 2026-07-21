import {ref} from 'vue'
import {actionScheduleRepository} from '@/modules/actions/repositories/actionScheduleRepository'
import {CRON_PRESETS} from '@/modules/actions/types/action'
import type {Action, ActionSchedule} from '@/modules/actions/types/action'
import {useFormToast} from '@/composables/useFormToast'
import {useZodForm} from '@/composables/useZodForm'
import {actionScheduleSchema} from '@/modules/actions/schemas/actionScheduleSchema'

export function useActionScheduleModal(onSaved: (id: string, schedule: ActionSchedule | null) => void) {
    const show = ref(false)
    const loading = ref(false)
    const action = ref<Action | null>(null)

    const {formData: form, errors, formError, submitting, submit, reset} =
        useZodForm(actionScheduleSchema, {
            enabled: true,
            cron: '0 9 * * *',
            timezone: 'Europe/Moscow',
            input: {source: 'schedule'} as Record<string, unknown>,
            options: {} as Record<string, unknown>,
            settings: {} as Record<string, unknown>,
        })

    const formToast = useFormToast({
        created: 'Расписание создано',
        updated: 'Расписание сохранено',
        deleted: 'Расписание удалено',
    })

    function fillForm(schedule: ActionSchedule | null | undefined): void {
        reset({
            enabled: schedule?.enabled ?? true,
            cron: schedule?.cron ?? '0 9 * * *',
            timezone: schedule?.timezone ?? 'Europe/Moscow',
            input: schedule?.input ?? {source: 'schedule'},
            options: schedule?.options ?? {},
            settings: schedule?.settings ?? {},
        })
    }

    async function open(item: Action): Promise<void> {
        action.value = item
        fillForm(item.schedule)
        show.value = true

        loading.value = true
        try {
            const schedule = await actionScheduleRepository.get(item.id)
            if (!show.value || action.value?.id !== item.id) return
            action.value = {...item, schedule}
            fillForm(schedule)
        } catch (e: unknown) {
            formError.value = e instanceof Error ? e.message : 'Не удалось загрузить расписание.'
        } finally {
            loading.value = false
        }
    }

    function close(): void {
        show.value = false
        action.value = null
    }

    async function save(): Promise<void> {
        if (!action.value) return
        const actionId = action.value.id
        const isUpdate = action.value.schedule !== null && action.value.schedule !== undefined
        try {
            await submit(async (data) => {
                const schedule = await actionScheduleRepository.upsert(actionId, {
                    enabled: data.enabled,
                    cron: data.cron.trim() || null,
                    timezone: data.timezone,
                    input: data.input,
                    options: data.options,
                    settings: data.settings,
                })
                onSaved(actionId, schedule)
            })
            close()
            formToast.saved(isUpdate)
        } catch { /* errors уже в форме */
        }
    }

    async function remove(): Promise<void> {
        if (!action.value) return
        try {
            await actionScheduleRepository.remove(action.value.id)
            onSaved(action.value.id, null)
            close()
            formToast.deleted()
        } catch (e: unknown) {
            formError.value = e instanceof Error ? e.message : 'Ошибка удаления.'
            formToast.error(e, 'Ошибка удаления.')
        }
    }

    return {
        show, loading, saving: submitting, error: formError, errors,
        action, form, presets: CRON_PRESETS,
        applyPreset: (cron: string) => {
            form.cron = cron
        },
        open, close, save, remove,
    }
}
