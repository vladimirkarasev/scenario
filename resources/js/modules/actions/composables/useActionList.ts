import {computed, onMounted, ref, watch} from 'vue'
import {actionRepository} from '@/modules/actions/repositories/actionRepository'
import {actionTypeRepository} from '@/modules/actions/repositories/actionTypeRepository'
import type {Action, ActionTypeMeta} from '@/modules/actions/types/action'

const PER_PAGE = 8

export function useActionList() {
    const actions = ref<Action[]>([])
    const actionTypes = ref<ActionTypeMeta[]>([])
    const loading = ref(false)
    const error = ref('')
    const search = ref('')
    const currentPage = ref(1)

    const typeLabels = computed<Record<string, string>>(() => {
        const map: Record<string, string> = {}
        for (const meta of actionTypes.value) map[meta.value] = meta.label
        return map
    })

    const activeCount = computed(() => actions.value.filter(a => a.is_active).length)
    const scheduledCount = computed(() => actions.value.filter(a => a.schedule?.enabled).length)

    const filtered = computed(() => {
        const q = search.value.toLowerCase().trim()
        if (!q) return actions.value
        return actions.value.filter(a =>
            a.name.toLowerCase().includes(q)
            || a.key.toLowerCase().includes(q)
            || (a.description ?? '').toLowerCase().includes(q),
        )
    })

    const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
    const paged = computed(() => filtered.value.slice((currentPage.value - 1) * PER_PAGE, currentPage.value * PER_PAGE))

    watch(search, () => {
        currentPage.value = 1
    })
    watch(totalPages, (n) => {
        if (currentPage.value > n) currentPage.value = n
    })

    async function load(): Promise<void> {
        loading.value = true
        error.value = ''
        try {
            const [actionsRes, typesRes] = await Promise.all([
                actionRepository.list(new URLSearchParams({'page[size]': '100'})),
                actionTypeRepository.list(),
            ])
            actions.value = actionsRes
            actionTypes.value = typesRes
        } catch (e: unknown) {
            error.value = e instanceof Error ? e.message : String(e)
        } finally {
            loading.value = false
        }
    }

    function upsert(action: Action): void {
        const idx = actions.value.findIndex(a => a.id === action.id)
        if (idx >= 0) actions.value[idx] = action
        else actions.value = [...actions.value, action].sort((l, r) => l.name.localeCompare(r.name))
    }

    function remove(id: string): void {
        actions.value = actions.value.filter(a => a.id !== id)
    }

    onMounted(load)

    return {
        actions, actionTypes, loading, error, search, currentPage,
        typeLabels, activeCount, scheduledCount,
        filtered, paged, totalPages,
        load, upsert, remove,
        perPage: PER_PAGE,
    }
}
