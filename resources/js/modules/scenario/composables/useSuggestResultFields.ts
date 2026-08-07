import {onMounted, ref, watch, type Ref} from 'vue'
import {webhookRepository} from '@/modules/proxy/repositories/webhookRepository'
import type {WebhookField} from '@/modules/proxy/types/webhook'

export function useSuggestResultFields(proxyUuidRef: Ref<string>) {
    const fields = ref<WebhookField[]>([])
    const loading = ref(false)

    async function load(uuid: string): Promise<void> {
        loading.value = true
        try {
            fields.value = await webhookRepository.responseFields(uuid)
        } catch {
            fields.value = []
        } finally {
            loading.value = false
        }
    }

    onMounted(() => {
        if (proxyUuidRef.value) void load(proxyUuidRef.value)
    })

    watch(proxyUuidRef, (uuid) => {
        if (uuid) void load(uuid)
        else fields.value = []
    })

    return {fields, loading}
}
