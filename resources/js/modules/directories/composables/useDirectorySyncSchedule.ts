import {onMounted, ref} from 'vue'
import {directorySyncScheduleRepository} from '@/modules/directories/repositories/directorySyncScheduleRepository'

export const CRON_PRESETS = [
    {label: 'Каждый час', cron: '0 * * * *'},
    {label: 'Каждые 6 часов', cron: '0 */6 * * *'},
    {label: 'Ежедневно в 09:00', cron: '0 9 * * *'},
    {label: 'Еженедельно (Пн 09:00)', cron: '0 9 * * 1'},
]

export function useDirectorySyncSchedule(directoryId: string) {
    const enabled = ref(false)
    const cron = ref('')
    const timezone = ref('')
    const nextRunAt = ref<string | null>(null)
    const lastRunAt = ref<string | null>(null)
    const loading = ref(false)
    const saving = ref(false)
    const error = ref('')

    async function load(): Promise<void> {
        loading.value = true
        try {
            const schedule = await directorySyncScheduleRepository.get(directoryId)
            if (schedule) {
                enabled.value = schedule.enabled
                cron.value = schedule.cron ?? ''
                timezone.value = schedule.timezone
                nextRunAt.value = schedule.next_run_at
                lastRunAt.value = schedule.last_run_at
            }
        } catch { /* silent */
        } finally {
            loading.value = false
        }
    }

    async function save(): Promise<void> {
        saving.value = true
        error.value = ''
        try {
            const schedule = await directorySyncScheduleRepository.save(directoryId, {
                enabled: enabled.value,
                cron: cron.value.trim() || null,
                timezone: timezone.value || null,
            })
            nextRunAt.value = schedule?.next_run_at ?? null
            lastRunAt.value = schedule?.last_run_at ?? null
        } catch (e: unknown) {
            error.value = e instanceof Error ? e.message : 'Не удалось сохранить расписание.'
        } finally {
            saving.value = false
        }
    }

    onMounted(load)

    return {enabled, cron, timezone, nextRunAt, lastRunAt, loading, saving, error, load, save}
}
