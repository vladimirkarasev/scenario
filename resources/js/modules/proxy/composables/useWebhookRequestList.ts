import {useUrlSearchParams} from '@vueuse/core'
import {computed, onMounted, ref, watch} from 'vue'
import {webhookRequestRepository} from '@/modules/proxy/repositories/webhookRequestRepository'
import type {WebhookRequestLog, WebhookRequestPage} from '@/modules/proxy/types/webhook'

const PER_PAGE = 20

export type WebhookRequestListParams = {
    'filter[search]'?: string | string[]
    'filter[endpoint_id]'?: string | string[]
    'filter[status]'?: string | string[]
    'page[number]'?: string | string[]
    'page[size]'?: string | string[]
}

function first(v: string | string[] | undefined): string {
    if (!v) return ''
    return Array.isArray(v) ? (v[0] ?? '') : v
}

export function useWebhookRequestList() {
    const params = useUrlSearchParams<WebhookRequestListParams>('history', {removeNullishValues: true})
    const loading = ref(false)
    const items = ref<WebhookRequestLog[]>([])
    const meta = ref<WebhookRequestPage['meta']>({current_page: 1, last_page: 1, per_page: PER_PAGE, total: 0})

    async function load(): Promise<void> {
        loading.value = true
        try {
            const qs = new URLSearchParams(window.location.search)
            qs.set('page[size]', String(PER_PAGE))
            if (!qs.has('page[number]')) qs.set('page[number]', '1')
            const result = await webhookRequestRepository.list(qs)
            items.value = result.data
            meta.value = result.meta
        } catch { /* silent */
        } finally {
            loading.value = false
        }
    }

    const search = computed({
        get: () => first(params['filter[search]']),
        set: (v: string) => {
            params['filter[search]'] = v || undefined
            params['page[number]'] = undefined
        },
    })

    const page = computed({
        get: () => Number(first(params['page[number]'])) || 1,
        set: (v: number) => {
            params['page[number]'] = v > 1 ? String(v) : undefined
        },
    })

    let searchTimer: ReturnType<typeof setTimeout> | null = null
    watch(() => params['filter[search]'], () => {
        if (searchTimer) clearTimeout(searchTimer)
        searchTimer = setTimeout(load, 300)
    })
    watch(
        [() => params['page[number]'], () => params['filter[endpoint_id]'], () => params['filter[status]']],
        load,
    )

    onMounted(load)

    return {params, loading, items, meta, search, page, load}
}
