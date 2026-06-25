import {beforeEach, describe, expect, it, vi} from 'vitest'
import {actionRepository, normalizeAction} from '@/modules/actions/repositories/actionRepository'
import {destroyJson, getJson, sendJson} from '@/lib/http'

vi.mock('@/lib/http', () => ({
    destroyJson: vi.fn(),
    getJson: vi.fn(),
    sendJson: vi.fn(),
}))

describe('actionRepository', () => {
    beforeEach(() => vi.clearAllMocks())

    it('нормализует нестабильные типы JSON API', () => {
        expect(normalizeAction({
            id: 'action-1',
            attributes: {
                name: 123,
                slug: 'send',
                description: '',
                is_active: 1,
                config: [],
                schema: {type: 'object'},
                input_fields: [
                    null,
                    {key: '', label: 'skip'},
                    {key: 'email', label: '', type: '', required: 1, default: 'a@b.c'},
                ],
                category_ids: [1, '2'],
            },
            relationships: {
                schedule: {
                    data: {
                        id: '7',
                        action_id: 99,
                        enabled: 1,
                        cron: '',
                        timezone: '',
                        input: [],
                        options: {retry: true},
                    },
                },
            },
        })).toMatchObject({
            id: 'action-1',
            name: '123',
            description: null,
            type: 'template_file',
            is_active: true,
            config: null,
            schema: {type: 'object'},
            input_fields: [{
                key: 'email',
                label: 'email',
                type: 'string',
                required: true,
                default: 'a@b.c',
            }],
            category_ids: ['1', '2'],
            schedule: {
                id: 7,
                action_id: '99',
                cron: null,
                timezone: 'Europe/Moscow',
                input: null,
                options: {retry: true},
            },
        })
    })

    it('формирует команду последовательного запуска', async () => {
        vi.mocked(sendJson).mockResolvedValue(undefined)

        await actionRepository.run('mail.send', 'action-1', {email: 'a@b.c'})

        expect(sendJson).toHaveBeenCalledWith('/api/actions/run', {
            body: {
                mode: 'sequential',
                actions: {'mail.send': 'action-1'},
                input: {email: 'a@b.c'},
            },
            fallbackMessage: 'Не удалось запустить action.',
        })
    })

    it('использует корректные endpoints для чтения и удаления', async () => {
        vi.mocked(getJson).mockResolvedValue({data: []})
        vi.mocked(destroyJson).mockResolvedValue(null)

        await actionRepository.list(new URLSearchParams({page: '2'}))
        await actionRepository.remove('action-1')

        expect(getJson).toHaveBeenCalledWith('/api/actions?page=2', 'Не удалось загрузить actions.')
        expect(destroyJson).toHaveBeenCalledWith('/api/actions/action-1', 'Не удалось удалить action.')
    })
})
