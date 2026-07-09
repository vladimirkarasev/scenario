import {beforeEach, describe, expect, it, vi} from 'vitest'
import {directorySchema} from '@/modules/directories/schemas/directorySchema'
import {directorySectionSchema} from '@/modules/directories/schemas/directorySectionSchema'
import {directoryRepository} from '@/modules/directories/repositories/directoryRepository'
import {directoryFeedRepository} from '@/modules/directories/repositories/directoryFeedRepository'
import {directorySyncScheduleRepository} from '@/modules/directories/repositories/directorySyncScheduleRepository'
import {SOURCE_TYPES} from '@/modules/directories/sourceTypes'
import {destroyJson, getJson, sendJson, sendMultipart} from '@/lib/http'

vi.mock('@/lib/http', () => ({
    destroyJson: vi.fn(),
    getJson: vi.fn(),
    sendJson: vi.fn(),
    sendMultipart: vi.fn(),
}))

describe('directories module', () => {
    beforeEach(() => vi.clearAllMocks())

    it('описывает все поддерживаемые источники', () => {
        expect(SOURCE_TYPES.map(type => type.id)).toEqual(['manual', 'excel', 'api', 'external'])
        expect(new Set(SOURCE_TYPES.map(type => type.id)).size).toBe(SOURCE_TYPES.length)
    })

    it('валидирует справочник и раздел', () => {
        const base = {
            name: 'Города',
            description: null,
            category_ids: [],
            source_type: 'manual',
            match_by: null,
            fields: [],
        }
        expect(directorySchema.safeParse({...base, slug: 'cities_2026'}).success).toBe(true)
        expect(directorySchema.safeParse({...base, slug: 'Города'}).success).toBe(false)
        expect(directorySectionSchema.safeParse({name: '', parent_id: null}).success).toBe(false)
    })

    it('нормализует id справочников, элементов, версий и импортов', async () => {
        vi.mocked(getJson)
            .mockResolvedValueOnce({data: [{id: 'dir-1', type: 'directories', attributes: {name: 'Города'}}], meta: {total: 1}})
            .mockResolvedValueOnce({data: [{id: '12', type: 'items', attributes: {values: {name: 'Москва'}}}]})
            .mockResolvedValueOnce({data: [{id: '3', type: 'versions', attributes: {status: 'active'}}]})
            .mockResolvedValueOnce({data: [{id: '4', type: 'imports', attributes: {status: 'done'}}]})

        await expect(directoryRepository.list(new URLSearchParams())).resolves.toEqual({
            items: [{id: 'dir-1', name: 'Города'}],
            meta: {total: 1},
        })
        await expect(directoryRepository.items('dir-1', new URLSearchParams())).resolves.toEqual({
            items: [{id: 12, values: {name: 'Москва'}}],
        })
        await expect(directoryRepository.versions('dir-1')).resolves.toEqual({
            items: [{id: 3, status: 'active'}],
        })
        await expect(directoryRepository.imports('dir-1')).resolves.toEqual({
            items: [{id: 4, status: 'done'}],
        })
    })

    it('собирает настройки версии без undefined-полей', async () => {
        vi.mocked(sendJson).mockResolvedValue({
            data: {id: '2', type: 'versions', attributes: {source_type: 'api'}},
        })

        await directoryRepository.updateVersionSettings('dir-1', 2, 'api', {
            add_new: true,
            update_existing: true,
            delete_unused: false,
        }, {
            allow_other: true,
            other_label: 'Другое',
        })

        expect(sendJson).toHaveBeenCalledWith('/api/directories/dir-1/versions/2/settings', {
            method: 'PATCH',
            body: {
                source_type: 'api',
                sync_options: {
                    add_new: true,
                    update_existing: true,
                    delete_unused: false,
                },
                allow_other: true,
                other_label: 'Другое',
            },
            fallbackMessage: 'Не удалось сохранить настройки версии.',
        })
    })

    it('передаёт FormData при импорте Excel', async () => {
        const body = new FormData()
        vi.mocked(sendMultipart).mockResolvedValue({
            data: {id: '8', type: 'imports', attributes: {status: 'pending'}},
        })

        await expect(directoryRepository.importExcel('dir-1', body)).resolves.toEqual({
            item: {id: 8, status: 'pending'},
        })
        expect(sendMultipart).toHaveBeenCalledWith('/api/directories/dir-1/imports', {
            method: 'POST',
            body,
            fallbackMessage: 'Не удалось запустить импорт.',
        })
    })

    it('делегирует feed и sync schedule правильным API', async () => {
        vi.mocked(getJson)
            .mockResolvedValueOnce({data: [], pagination: {total: 0}})
            .mockResolvedValueOnce({data: null})
        vi.mocked(destroyJson).mockResolvedValue(null)

        await directoryFeedRepository.fetch(new URLSearchParams({search: 'city'}))
        await expect(directorySyncScheduleRepository.get('dir-1')).resolves.toBeNull()
        await directorySyncScheduleRepository.remove('dir-1')

        expect(getJson).toHaveBeenNthCalledWith(
            1,
            '/api/directories/feed?search=city',
            'Не удалось загрузить справочники.',
        )
        expect(destroyJson).toHaveBeenCalledWith(
            '/api/actions/directories/dir-1/sync-schedule',
            'Не удалось удалить расписание синхронизации.',
        )
    })
})
