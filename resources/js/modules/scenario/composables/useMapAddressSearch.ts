import {onBeforeUnmount, ref, watch} from 'vue'
import {useYandexGeocode} from '@/modules/scenario/composables/useYandexGeocode'
import type {GeocodeResult} from '@/modules/scenario/types/yandex-map'

const SEARCH_MIN_LENGTH = 3
const SEARCH_DELAY = 300

export function useMapAddressSearch(limit = 5) {
    const query = ref('')
    const searching = ref(false)
    const results = ref<GeocodeResult[]>([])
    const {forwardGeocode} = useYandexGeocode()
    let searchTimer: ReturnType<typeof setTimeout> | null = null
    let searchSequence = 0

    function clear(): void {
        query.value = ''
        results.value = []
        searching.value = false
        searchSequence += 1
    }

    async function search(value: string, sequence: number): Promise<void> {
        searching.value = true

        try {
            const found = await forwardGeocode(value, limit)
            if (sequence === searchSequence) results.value = found
        } catch (error: unknown) {
            if (sequence === searchSequence) results.value = []
            console.error('[useMapAddressSearch] forwardGeocode failed', error)
        } finally {
            if (sequence === searchSequence) searching.value = false
        }
    }

    watch(query, (value) => {
        if (searchTimer) clearTimeout(searchTimer)

        const normalizedQuery = value.trim()
        const sequence = ++searchSequence

        if (normalizedQuery.length < SEARCH_MIN_LENGTH) {
            searching.value = false
            results.value = []
            return
        }

        searchTimer = setTimeout(() => void search(normalizedQuery, sequence), SEARCH_DELAY)
    })

    onBeforeUnmount(() => {
        if (searchTimer) clearTimeout(searchTimer)
    })

    return {
        clear,
        query,
        results,
        searching,
    }
}
