import {computed, onMounted, ref, watch} from 'vue'
import {webhookRepository} from '@/modules/proxy/repositories/webhookRepository'
import type {WebhookEndpoint} from '@/modules/proxy/types/webhook'

const PER_PAGE = 15

export function useWebhookList() {
    const loading = ref(false)
    const endpoints = ref<WebhookEndpoint[]>([])
    const search = ref('')
    const page = ref(1)

    async function load(): Promise<void> {
        loading.value = true
        try {
            endpoints.value = await webhookRepository.list()
        } catch { /* silent */
        } finally {
            loading.value = false
        }
    }

    onMounted(load)

    const filtered = computed(() => {
        const q = search.value.toLowerCase().trim()
        if (!q) return endpoints.value
        return endpoints.value.filter(e =>
            e.name.toLowerCase().includes(q) ||
            e.code.toLowerCase().includes(q) ||
            (e.description ?? '').toLowerCase().includes(q),
        )
    })

    const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)))
    const paged = computed(() =>
        filtered.value.slice((page.value - 1) * PER_PAGE, page.value * PER_PAGE),
    )

    watch(search, () => {
        page.value = 1
    })
    watch(totalPages, (n) => {
        if (page.value > n) page.value = n
    })

    return {loading, endpoints, search, page, filtered, paged, totalPages, PER_PAGE, load}
}
