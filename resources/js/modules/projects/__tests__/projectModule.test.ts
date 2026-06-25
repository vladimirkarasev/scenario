import {beforeEach, describe, expect, it, vi} from 'vitest'
import {projectSchema} from '@/modules/projects/schemas/projectSchema'
import {projectRepository} from '@/modules/projects/repositories/projectRepository'
import {destroyJson, getJson, sendJson} from '@/lib/http'

vi.mock('@/lib/http', () => ({
    destroyJson: vi.fn(),
    getJson: vi.fn(),
    sendJson: vi.fn(),
}))

const rawProject = {
    id: 'project-1',
    attributes: {
        name: 'Проект',
        sitekey: 'site-key',
        host: 'example.test',
        shared_secret: null,
        is_active: true,
        created_at: '2026-06-24',
    },
}

describe('projects module', () => {
    beforeEach(() => vi.clearAllMocks())

    it('требует секрет длиной не менее восьми символов', () => {
        const base = {name: 'Проект', sitekey: 'key', host: 'example.test', is_active: true}
        expect(projectSchema.safeParse({...base, shared_secret: '12345678'}).success).toBe(true)
        expect(projectSchema.safeParse({...base, shared_secret: '1234567'}).success).toBe(false)
    })

    it('нормализует ответы list/find/create/update', async () => {
        vi.mocked(getJson)
            .mockResolvedValueOnce({data: [rawProject], meta: {total: 1}})
            .mockResolvedValueOnce({data: rawProject})
        vi.mocked(sendJson).mockResolvedValue({data: rawProject})

        await expect(projectRepository.list(new URLSearchParams())).resolves.toMatchObject({
            data: [{id: 'project-1', shared_secret: null}],
            meta: {total: 1},
        })
        await expect(projectRepository.find('project-1')).resolves.toMatchObject({id: 'project-1'})
        await expect(projectRepository.create({
            name: 'Проект',
            sitekey: 'key',
            host: 'example.test',
            shared_secret: '12345678',
            is_active: true,
        })).resolves.toMatchObject({id: 'project-1'})
    })

    it('удаляет проект по id', async () => {
        vi.mocked(destroyJson).mockResolvedValue(null)
        await projectRepository.remove('project-1')
        expect(destroyJson).toHaveBeenCalledWith('/api/projects/project-1', 'Не удалось удалить проект.')
    })
})
