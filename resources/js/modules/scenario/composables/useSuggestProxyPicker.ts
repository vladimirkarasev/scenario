import {computed, ref} from 'vue'
import {webhookRepository} from '@/modules/proxy/repositories/webhookRepository'
import type {WebhookEndpoint} from '@/modules/proxy/types/webhook'

/**
 * Загрузка и выбор proxy-эндпоинтов типа `suggest` для настройки block-поля «Подсказки».
 * Источник списка — webhookRepository.listByType('suggest').
 */
export function useSuggestProxyPicker(getProxyUuid: () => string) {
    const proxies = ref<WebhookEndpoint[]>([])
    const loading = ref(false)
    const loaded = ref(false)

    async function load(): Promise<void> {
        if (loaded.value || loading.value) return
        loading.value = true
        try {
            proxies.value = await webhookRepository.listByType('suggest')
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
