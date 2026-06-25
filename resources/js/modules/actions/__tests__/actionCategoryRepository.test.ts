import {beforeEach, describe, expect, it, vi} from 'vitest'
import {actionCategoryRepository} from '@/modules/actions/repositories/actionCategoryRepository'
import {destroyJson, getJson, sendJson} from '@/lib/http'

vi.mock('@/lib/http', () => ({
    destroyJson: vi.fn(),
    getJson: vi.fn(),
    sendJson: vi.fn(),
}))

const categoryJson = {
    id: 'category-1',
    attributes: {
        parent_id: null,
        name: 'Системные действия',
        is_active: true,
        is_system: true,
        created_at: '2026-06-24T10:00:00Z',
        updated_at: null,
    },
    relationships: {
        children: {meta: {count: 3}},
    },
}

describe('actionCategoryRepository', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('нормализует категории из JSON API', async () => {
        vi.mocked(getJson).mockResolvedValue({data: [categoryJson]})

        await expect(actionCategoryRepository.all()).resolves.toEqual([{
            id: 'category-1',
            parent_id: null,
            name: 'Системные действия',
            is_active: true,
            is_system: true,
            children_count: 3,
            created_at: '2026-06-24T10:00:00Z',
            updated_at: null,
        }])
    })

    it.each([
        [null, 'filter%5Bparent_id%5D=null'],
        ['parent-1', 'filter%5Bparent_id%5D=parent-1'],
    ])('передаёт parent_id=%s в фильтр', async (parentId, expectedQuery) => {
        vi.mocked(getJson).mockResolvedValue({data: []})

        await actionCategoryRepository.byParent(parentId)

        expect(getJson).toHaveBeenCalledWith(
            `/api/actions/categories?${expectedQuery}`,
            'Не удалось загрузить разделы.',
        )
    })

    it('использует корректные запросы для создания, обновления и удаления', async () => {
        const payload = {name: 'Новый раздел', parent_id: null, is_active: true}
        vi.mocked(sendJson).mockResolvedValue({data: categoryJson})
        vi.mocked(destroyJson).mockResolvedValue(null)

        await actionCategoryRepository.create(payload)
        await actionCategoryRepository.update('category-1', payload)
        await actionCategoryRepository.remove('category-1')

        expect(sendJson).toHaveBeenNthCalledWith(1, '/api/actions/categories', {
            body: payload,
            fallbackMessage: 'Не удалось создать раздел.',
        })
        expect(sendJson).toHaveBeenNthCalledWith(2, '/api/actions/categories/category-1', {
            method: 'PUT',
            body: payload,
            fallbackMessage: 'Не удалось обновить раздел.',
        })
        expect(destroyJson).toHaveBeenCalledWith(
            '/api/actions/categories/category-1',
            'Не удалось удалить раздел.',
        )
    })
})
