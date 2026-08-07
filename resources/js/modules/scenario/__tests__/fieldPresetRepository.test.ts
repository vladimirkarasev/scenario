import {beforeEach, describe, expect, it, vi} from 'vitest'
import {destroyJson, getJson, sendJson} from '@/lib/http'
import {fieldPresetRepository} from '@/modules/scenario/repositories/fieldPresetRepository'
import type {BlockField} from '@/modules/scenario/lib/scenario-block-fields'

vi.mock('@/lib/http', () => ({
    destroyJson: vi.fn(),
    getJson: vi.fn(),
    sendJson: vi.fn(),
}))

const field = {
    id: 'input_1',
    type: 'input',
    name: 'input_1',
    label: 'ФИО',
    required: true,
    varName: 'fio',
    placeholder: '',
    value: '',
} as BlockField

const preset = {
    id: 'preset-1',
    name: 'ФИО клиента',
    field_type: 'input' as const,
    field,
    schema_version: 1,
    created_at: null,
    updated_at: null,
}

describe('fieldPresetRepository', () => {
    beforeEach(() => vi.clearAllMocks())

    it('загружает проектные шаблоны', async () => {
        vi.mocked(getJson).mockResolvedValue({data: [preset]})

        await expect(fieldPresetRepository.list()).resolves.toEqual([preset])
        expect(getJson).toHaveBeenCalledWith(
            '/api/scenarios/field-presets',
            'Не удалось загрузить пользовательские поля.',
        )
    })

    it('создаёт и обновляет шаблон с полным снимком поля', async () => {
        vi.mocked(sendJson).mockResolvedValue({data: preset})
        const payload = {name: preset.name, field}

        await fieldPresetRepository.create(payload)
        await fieldPresetRepository.update(preset.id, payload)

        expect(sendJson).toHaveBeenNthCalledWith(1, '/api/scenarios/field-presets', {
            method: 'POST',
            body: payload,
            fallbackMessage: 'Не удалось сохранить пользовательское поле.',
        })
        expect(sendJson).toHaveBeenNthCalledWith(2, '/api/scenarios/field-presets/preset-1', {
            method: 'PUT',
            body: payload,
            fallbackMessage: 'Не удалось обновить пользовательское поле.',
        })
    })

    it('удаляет шаблон', async () => {
        vi.mocked(destroyJson).mockResolvedValue(null)

        await fieldPresetRepository.remove('preset-1')

        expect(destroyJson).toHaveBeenCalledWith(
            '/api/scenarios/field-presets/preset-1',
            'Не удалось удалить пользовательское поле.',
        )
    })
})
