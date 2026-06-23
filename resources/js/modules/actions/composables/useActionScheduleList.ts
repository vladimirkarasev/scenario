import {computed, onMounted, ref} from 'vue'
import {actionScheduleRepository} from '@/modules/actions/repositories/actionScheduleRepository'
import type {ScheduleListItem} from '@/modules/actions/types/action'

export function useActionScheduleList() {
    const items = ref<ScheduleListItem[]>([])
    const loading = ref(false)
    const error = ref('')
    const search = ref('')
    const showDisabled = ref(true)

    async function load(): Promise<void> {
        loading.value = true
        error.value = ''
        try {
            items.value = await actionScheduleRepository.list()
        } catch (e: unknown) {
            error.value = e instanceof Error ? e.message : String(e)
        } finally {
            loading.value = false
        }
    }

    const filtered = computed(() => {
        const q = search.value.toLowerCase().trim()
        return items.value.filter((s) => {
            if (!showDisabled.value && !s.enabled) return false
            if (!q) return true
            return (s.action?.name ?? '').toLowerCase().includes(q)
                || (s.action?.code ?? '').toLowerCase().includes(q)
                || (s.cron ?? '').toLowerCase().includes(q)
        })
    })

    const counts = computed(() => ({
        total: items.value.length,
        enabled: items.value.filter(s => s.enabled).length,
        disabled: items.value.filter(s => !s.enabled).length,
    }))

    onMounted(load)

    return {items, loading, error, search, showDisabled, filtered, counts, load}
}
