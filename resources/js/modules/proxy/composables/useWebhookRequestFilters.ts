import {computed, onMounted, ref} from 'vue'
import {webhookRepository} from '@/modules/proxy/repositories/webhookRepository'
import type {WebhookRequestListParams} from './useWebhookRequestList'

export const STATUS_OPTIONS = [
    {value: 'received', label: 'Получен'},
    {value: 'accepted', label: 'Принят'},
    {value: 'rejected', label: 'Отклонён'},
    {value: 'failed', label: 'Ошибка'},
    {value: 'processed', label: 'Обработан'},
]

type EndpointOption = { id: number; name: string }

function first(v: string | string[] | undefined): string {
    if (!v) return ''
    return Array.isArray(v) ? (v[0] ?? '') : v
}

export function useWebhookRequestFilters(params: WebhookRequestListParams) {
    const endpointOpen = ref(false)
    const statusOpen = ref(false)
    const endpointSearch = ref('')
    const endpointResults = ref<EndpointOption[]>([])
    const selectedEndpoint = ref<EndpointOption | null>(null)

    const selectedEndpointId = computed(() => first(params['filter[endpoint_id]']))
    const selectedStatusValue = computed(() => first(params['filter[status]']))

    const selectedStatusLabel = computed(() =>
        STATUS_OPTIONS.find(s => s.value === selectedStatusValue.value)?.label ?? null,
    )

    const hasFilters = computed(() => !!selectedEndpointId.value || !!selectedStatusValue.value)

    onMounted(async () => {
        const id = selectedEndpointId.value
        if (!id) return
        try {
            const e = await webhookRepository.find(Number(id))
            selectedEndpoint.value = {id: e.id, name: e.name}
        } catch { /* silent */
        }
    })

    let endpointTimer: ReturnType<typeof setTimeout> | null = null

    function onEndpointInput(): void {
        if (endpointTimer) clearTimeout(endpointTimer)
        const q = endpointSearch.value.trim()
        if (!q) {
            endpointResults.value = [];
            return
        }
        endpointTimer = setTimeout(async () => {
            try {
                const res = await webhookRepository.list(new URLSearchParams({'filter[search]': q}))
                endpointResults.value = res.map(e => ({id: e.id, name: e.name}))
            } catch { /* silent */
            }
        }, 250)
    }

    function selectEndpoint(e: EndpointOption): void {
        params['filter[endpoint_id]'] = String(e.id)
        params['page[number]'] = undefined
        selectedEndpoint.value = e
        endpointOpen.value = false
        endpointSearch.value = ''
        endpointResults.value = []
    }

    function clearEndpoint(): void {
        params['filter[endpoint_id]'] = undefined
        params['page[number]'] = undefined
        selectedEndpoint.value = null
    }

    function selectStatus(value: string): void {
        params['filter[status]'] = value
        params['page[number]'] = undefined
        statusOpen.value = false
    }

    function clearStatus(): void {
        params['filter[status]'] = undefined
        params['page[number]'] = undefined
    }

    function clear(): void {
        clearEndpoint()
        clearStatus()
    }

    function closeEndpointSoon(): void {
        setTimeout(() => {
            endpointOpen.value = false;
            endpointSearch.value = '';
            endpointResults.value = []
        }, 150)
    }

    function closeStatusSoon(): void {
        setTimeout(() => {
            statusOpen.value = false
        }, 150)
    }

    return {
        STATUS_OPTIONS,
        endpointOpen, statusOpen, endpointSearch, endpointResults,
        selectedEndpoint, selectedStatusValue, selectedStatusLabel, hasFilters,
        onEndpointInput, selectEndpoint, clearEndpoint, selectStatus, clearStatus, clear,
        closeEndpointSoon, closeStatusSoon,
    }
}
