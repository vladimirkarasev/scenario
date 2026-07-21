import {computed, ref} from 'vue'
import {webhookRepository} from '@/modules/proxy/repositories/webhookRepository'
import type {WebhookEndpoint} from '@/modules/proxy/types/webhook'

export function useSuggestProxyPicker(getProxyUuid: () => string) {
    const proxies = ref<WebhookEndpoint[]>([])
    const loading = ref(false)
    const loaded = ref(false)

    async function load(): Promise<void> {
        if (loaded.value || loading.value) return
        loading.value = true
        try {
            proxies.value = await webhookRepository.list(
                new URLSearchParams({'filter[type]': 'suggest'}),
            )
            loaded.value = true
        } finally {
            loading.value = false
        }
    }

    const selected = computed<WebhookEndpoint | null>(() => {
        const uuid = getProxyUuid()
        return uuid ? (proxies.value.find((p) => p.uuid === uuid) ?? null) : null
    })

    return {proxies, loading, loaded, load, selected}
}
