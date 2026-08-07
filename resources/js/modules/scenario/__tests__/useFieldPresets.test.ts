import {beforeEach, describe, expect, it, vi} from 'vitest'
import {fieldPresetRepository} from '@/modules/scenario/repositories/fieldPresetRepository'
import {useFieldPresets} from '@/modules/scenario/composables/useFieldPresets'
import type {ScenarioFieldPreset} from '@/modules/scenario/types/field-preset'

vi.mock('@/modules/scenario/repositories/fieldPresetRepository', () => ({
    fieldPresetRepository: {
        list: vi.fn(),
        create: vi.fn(),
        update: vi.fn(),
        remove: vi.fn(),
    },
}))

vi.mock('@/composables/useFormToast', () => ({
    useFormToast: () => ({saved: vi.fn(), deleted: vi.fn(), error: vi.fn()}),
}))

function deferred<T>() {
    let resolve!: (value: T) => void
    const promise = new Promise<T>((next) => {
        resolve = next
    })

    return {promise, resolve}
}

const field = {
    id: 'input-1',
    type: 'input' as const,
    name: 'input-1',
    label: 'ФИО',
    required: false,
    varName: 'fio',
    placeholder: '',
    value: '',
}

function preset(id: string, name: string): ScenarioFieldPreset {
    return {
        id,
        name,
        field_type: 'input',
        field,
        schema_version: 1,
        created_at: null,
        updated_at: null,
    }
}

describe('useFieldPresets', () => {
    beforeEach(() => vi.clearAllMocks())

    it('не позволяет запоздавшей загрузке затереть созданный шаблон', async () => {
        const pendingList = deferred<ScenarioFieldPreset[]>()
        vi.mocked(fieldPresetRepository.list).mockReturnValue(pendingList.promise)
        vi.mocked(fieldPresetRepository.create).mockResolvedValue(preset('new', 'Новое поле'))
        const presets = useFieldPresets()

        const loadPromise = presets.load()
        await presets.save('Новое поле', field, null)
        pendingList.resolve([preset('old', 'Старый ответ')])
        await loadPromise

        expect(presets.presets.value.map((item) => item.id)).toEqual(['new'])
        expect(presets.loading.value).toBe(false)
    })

    it('применяет только последнюю из параллельных загрузок', async () => {
        const first = deferred<ScenarioFieldPreset[]>()
        const second = deferred<ScenarioFieldPreset[]>()
        vi.mocked(fieldPresetRepository.list)
            .mockReturnValueOnce(first.promise)
            .mockReturnValueOnce(second.promise)
        const presets = useFieldPresets()

        const firstLoad = presets.load()
        const secondLoad = presets.load()
        second.resolve([preset('latest', 'Актуальный')])
        await secondLoad
        first.resolve([preset('stale', 'Устаревший')])
        await firstLoad

        expect(presets.presets.value.map((item) => item.id)).toEqual(['latest'])
    })
})
