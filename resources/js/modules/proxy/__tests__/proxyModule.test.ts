import {beforeEach, describe, expect, it, vi} from 'vitest'
import {webhookSchema} from '@/modules/proxy/schemas/webhookSchema'
import {webhookRepository} from '@/modules/proxy/repositories/webhookRepository'
import {webhookRequestRepository} from '@/modules/proxy/repositories/webhookRequestRepository'
import {getJson, sendJson} from '@/lib/http'

vi.mock('@/lib/http', () => ({
    getJson: vi.fn(),
    sendJson: vi.fn(),
}))

describe('proxy module', () => {
    beforeEach(() => vi.clearAllMocks())

    it('валидирует mock status и JSON-контейнеры', () => {
        const base = {
            name: 'Suggest',
            code: 'suggest',
            handler_class: 'Handler',
            type: 'webhook',
            description: '',
            is_active: true,
            is_mocked: true,
            category_ids: [],
            connection_id: null,
            config: {},
        }
        const schema = webhookSchema({})
        expect(schema.safeParse({...base, mocks: [{
            name: null, status: 200, body: [], headers: null, is_active: true,
        }]}).success).toBe(true)
        expect(schema.safeParse({...base, mocks: [{
            name: null, status: 99, body: 'invalid', headers: null, is_active: false,
        }]}).success).toBe(false)
    })

    it('требует доступ, если обработчик привязан к типу доступа', () => {
        const base = {
            name: 'Suggest',
            code: 'suggest',
            handler_class: 'Handler',
            type: 'webhook',
            description: '',
            is_active: true,
            is_mocked: true,
            category_ids: [],
            config: {},
            mocks: [],
        }
        const schema = webhookSchema({Handler: 'Module\\Proxy\\Credentials\\AutoCrm\\AutoCrmCredential'})

        const withoutConnection = schema.safeParse({...base, connection_id: null})
        expect(withoutConnection.success).toBe(false)
        if (!withoutConnection.success) {
            expect(withoutConnection.error.issues[0]?.path).toEqual(['connection_id'])
        }

        expect(schema.safeParse({...base, connection_id: 5}).success).toBe(true)

        const noCredentialHandler = webhookSchema({})
        expect(noCredentialHandler.safeParse({...base, connection_id: null}).success).toBe(true)
    })

    it('кодирует type и search фильтры', async () => {
        vi.mocked(getJson).mockResolvedValue({data: []})

        await webhookRepository.list(new URLSearchParams({'filter[type]': 'suggest value'}))
        await webhookRepository.list(new URLSearchParams({'filter[search]': 'Иван + Пётр'}))

        expect(getJson).toHaveBeenNthCalledWith(
            1,
            '/api/proxy/endpoints?filter%5Btype%5D=suggest+value',
            'Не удалось загрузить эндпоинты.',
        )
        expect(getJson).toHaveBeenNthCalledWith(
            2,
            '/api/proxy/endpoints?filter%5Bsearch%5D=%D0%98%D0%B2%D0%B0%D0%BD+%2B+%D0%9F%D1%91%D1%82%D1%80',
            'Не удалось загрузить эндпоинты.',
        )
    })

    it('обновляет endpoint полным payload', async () => {
        const payload = {
            name: 'Suggest',
            code: 'suggest',
            type: 'webhook',
            description: null,
            is_active: true,
            is_mocked: false,
            handler_class: 'Handler',
            config: {},
            mock_responses: [],
        }
        vi.mocked(sendJson).mockResolvedValue({data: {id: '1', attributes: {}}})

        await webhookRepository.update(1, payload)

        expect(sendJson).toHaveBeenCalledWith('/api/proxy/endpoints/1', {
            method: 'PUT',
            body: payload,
            fallbackMessage: 'Не удалось сохранить интеграцию.',
        })
    })

    it('нормализует список и детальный webhook request', async () => {
        const attributes = {
            request_id: 'req-1',
            endpoint: null,
            endpoint_id: null,
            status: 'ok',
            status_label: 'OK',
            status_color: 'green',
            is_mocked: false,
            ip: null,
            error: null,
            created_at: null,
        }
        vi.mocked(getJson)
            .mockResolvedValueOnce({data: [{id: 'log-1', attributes}], meta: {total: 1}})
            .mockResolvedValueOnce({data: [{id: 'unused'}]})

        await expect(webhookRequestRepository.list(new URLSearchParams())).resolves.toEqual({
            data: [{id: 'log-1', ...attributes}],
            meta: {total: 1},
        })
    })
})
