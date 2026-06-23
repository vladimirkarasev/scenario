import {computed, onMounted, ref} from 'vue'
import {actionRunRepository} from '@/modules/actions/repositories/actionRunRepository'
import type {ActionRun} from '@/modules/actions/types/action'

export type RunStatusFilter = 'all' | 'success' | 'failed' | 'skipped' | 'running'

export function useActionRunList() {
    const runs = ref<ActionRun[]>([])
    const loading = ref(false)
    const error = ref('')
    const search = ref('')
    const statusFilter = ref<RunStatusFilter>('all')

    async function load(): Promise<void> {
        loading.value = true
        error.value = ''
        try {
            runs.value = await actionRunRepository.list(new URLSearchParams({'page[size]': '200'}))
        } catch (e: unknown) {
            error.value = e instanceof Error ? e.message : String(e)
        } finally {
            loading.value = false
        }
    }

    const filtered = computed(() => {
        const q = search.value.toLowerCase().trim()
        return runs.value.filter((run) => {
            if (statusFilter.value !== 'all' && run.status !== statusFilter.value) return false
            if (!q) return true
            return String(run.id).includes(q)
                || (run.action_name ?? '').toLowerCase().includes(q)
                || (run.error ?? '').toLowerCase().includes(q)
                || (run.reason ?? '').toLowerCase().includes(q)
        })
    })

    const counts = computed(() => ({
        total: runs.value.length,
        success: runs.value.filter(r => r.status === 'success').length,
        failed: runs.value.filter(r => r.status === 'failed').length,
        skipped: runs.value.filter(r => r.status === 'skipped').length,
    }))

    onMounted(load)

    return {runs, loading, error, search, statusFilter, filtered, counts, load}
}
