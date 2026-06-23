import { computed, ref, watch } from 'vue'
import { webhookRepository } from '@/modules/proxy/repositories/webhookRepository'
import type { WebhookEndpoint, WebhookField } from '@/modules/proxy/types/webhook'

export function useDirectoryProxyPicker(
  getProxyUuid: () => string | null,
  setProxyUuid: (uuid: string) => void,
) {
  const open    = ref(false)
  const search  = ref('')
  const proxies = ref<WebhookEndpoint[]>([])
  const fields  = ref<WebhookField[]>([])
  const loading = ref(false)

  async function loadProxies(): Promise<void> {
    if (proxies.value.length) return
    loading.value = true
    try {
      proxies.value = await webhookRepository.list()
    } finally {
      loading.value = false
    }
  }

  async function loadFields(uuid: string | null | undefined): Promise<void> {
    if (!uuid) { fields.value = []; return }
    loading.value = true
    try {
      if (!proxies.value.length) await loadProxies()
      const proxy = proxies.value.find(p => p.uuid === uuid)
      if (!proxy) return
      fields.value = await webhookRepository.fields(proxy.uuid)
    } catch {
      fields.value = []
    } finally {
      loading.value = false
    }
  }

  watch(getProxyUuid, loadFields, { immediate: true })

  const filtered = computed(() => {
    const q = search.value.toLowerCase().trim()
    if (!q) return proxies.value
    return proxies.value.filter(p =>
      p.name.toLowerCase().includes(q)
      || p.code.toLowerCase().includes(q)
      || (p.description ?? '').toLowerCase().includes(q),
    )
  })

  const selected = computed(() => {
    const uuid = getProxyUuid()
    return uuid ? (proxies.value.find(p => p.uuid === uuid) ?? null) : null
  })

  function openPicker(): void {
    search.value = ''
    open.value   = true
    void loadProxies()
  }

  function selectProxy(proxy: WebhookEndpoint): void {
    setProxyUuid(proxy.uuid)
    open.value = false
  }

  return { open, search, fields, loading, filtered, selected, openPicker, selectProxy, loadFields }
}

export type DirectoryProxyPickerContext = ReturnType<typeof useDirectoryProxyPicker>
