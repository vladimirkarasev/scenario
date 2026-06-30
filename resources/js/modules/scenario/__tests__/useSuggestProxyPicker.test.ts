import {beforeEach, describe, expect, it, vi} from 'vitest'
import {ref} from 'vue'
import {useSuggestProxyPicker} from '@/modules/scenario/composables/useSuggestProxyPicker'
import {webhookRepository} from '@/modules/proxy/repositories/webhookRepository'
import type {WebhookEndpoint} from '@/modules/proxy/types/webhook'

vi.mock('@/modules/proxy/repositories/webhookRepository', () => ({
    webhookRepository: {
        listByType: vi.fn(),
    },
}))

function endpoint(uuid: string): WebhookEndpoint {
    return {
        id: 1,
        uuid,
        name: uuid,
        code: uuid,
        type: 'suggest',
        description: null,
        is_active: true,
        is_mocked: false,
        handler_class: '',
        connection_id: null,
        category_ids: [],
        method: 'POST',
        base_uri: null,
        credentials: {},
        secret_filled: {},
        receive_url: '',
        config: {},
        mock_responses: [],
        updated_at: null,
    }
}

describe('useSuggestProxyPicker', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('загружает suggest-эндпоинты один раз и вычисляет выбранный', async () => {
        const selectedUuid = ref('proxy-2')
        const items = [endpoint('proxy-1'), endpoint('proxy-2')]
        vi.mocked(webhookRepository.listByType).mockResolvedValue(items)
        const picker = useSuggestProxyPicker(() => selectedUuid.value)

        await picker.load()
        await picker.load()

        expect(webhookRepository.listByType).toHaveBeenCalledOnce()
        expect(webhookRepository.listByType).toHaveBeenCalledWith('suggest')
        expect(picker.loaded.value).toBe(true)
        expect(picker.loading.value).toBe(false)
        expect(picker.proxies.value).toEqual(items)
        expect(picker.selected.value).toEqual(items[1])

        selectedUuid.value = 'missing'
        expect(picker.selected.value).toBeNull()
    })

    it('сбрасывает loading и разрешает повторную загрузку после ошибки', async () => {
        vi.mocked(webhookRepository.listByType)
            .mockRejectedValueOnce(new Error('network error'))
            .mockResolvedValueOnce([endpoint('proxy-1')])
        const picker = useSuggestProxyPicker(() => '')

        await expect(picker.load()).rejects.toThrow('network error')
        expect(picker.loading.value).toBe(false)
        expect(picker.loaded.value).toBe(false)

        await picker.load()
        expect(webhookRepository.listByType).toHaveBeenCalledTimes(2)
        expect(picker.loaded.value).toBe(true)
    })
})
