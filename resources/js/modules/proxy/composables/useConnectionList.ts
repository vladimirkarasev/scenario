import {computed, onMounted, ref} from 'vue'
import {useLatestRequest} from '@/composables/useLatestRequest'
import {proxyConnectionRepository} from '@/modules/proxy/repositories/proxyConnectionRepository'
import type {ProxyConnection} from '@/modules/proxy/types/connection'

export function useConnectionList() {
    const connections = ref<ProxyConnection[]>([])
    const search = ref('')
    const {loading, error, execute} = useLatestRequest('Не удалось загрузить доступы.')

    async function load(): Promise<void> {
        const result = await execute(() => proxyConnectionRepository.list())
        if (result) connections.value = result
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

    return {loading, error, connections, search, filtered, load}
}
