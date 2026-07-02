import {computed, onMounted, ref} from 'vue'
import {proxyConnectionRepository} from '@/modules/proxy/repositories/proxyConnectionRepository'
import type {ProxyConnection} from '@/modules/proxy/types/connection'

export function useConnectionList() {
    const loading = ref(false)
    const connections = ref<ProxyConnection[]>([])
    const search = ref('')

    async function load(): Promise<void> {
        loading.value = true
        try {
            connections.value = await proxyConnectionRepository.list()
        } catch { /* silent */
        } finally {
            loading.value = false
        }
    }

    onMounted(load)

    const filtered = computed(() => {
        const q = search.value.toLowerCase().trim()
        if (!q) return connections.value
        return connections.value.filter(c =>
            c.name.toLowerCase().includes(q) ||
            c.credential_label.toLowerCase().includes(q),
        )
    })

    return {loading, connections, search, filtered, load}
}
