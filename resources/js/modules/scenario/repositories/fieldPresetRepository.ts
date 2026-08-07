import {destroyJson, getJson, sendJson} from '@/lib/http'
import type {
    ScenarioFieldPreset,
    ScenarioFieldPresetPayload,
} from '@/modules/scenario/types/field-preset'

export const fieldPresetRepository = {
    async list(): Promise<ScenarioFieldPreset[]> {
        const response = await getJson<{data: ScenarioFieldPreset[]}>(
            '/api/scenarios/field-presets',
            'Не удалось загрузить пользовательские поля.',
        )

        return response.data
    },

    async create(payload: ScenarioFieldPresetPayload): Promise<ScenarioFieldPreset> {
        const response = await sendJson<{data: ScenarioFieldPreset}>('/api/scenarios/field-presets', {
            method: 'POST',
            body: payload,
            fallbackMessage: 'Не удалось сохранить пользовательское поле.',
        })

        return response.data
    },

    async update(id: string, payload: ScenarioFieldPresetPayload): Promise<ScenarioFieldPreset> {
        const response = await sendJson<{data: ScenarioFieldPreset}>(`/api/scenarios/field-presets/${id}`, {
            method: 'PUT',
            body: payload,
            fallbackMessage: 'Не удалось обновить пользовательское поле.',
        })

        return response.data
    },

    async remove(id: string): Promise<void> {
        await destroyJson(
            `/api/scenarios/field-presets/${id}`,
            'Не удалось удалить пользовательское поле.',
        )
    },
}
