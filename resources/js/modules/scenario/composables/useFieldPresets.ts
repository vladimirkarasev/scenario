import {ref} from 'vue'
import {useFormToast} from '@/composables/useFormToast'
import {fieldPresetRepository} from '@/modules/scenario/repositories/fieldPresetRepository'
import type {BlockField} from '@/modules/scenario/lib/scenario-block-fields'
import type {ScenarioFieldPreset} from '@/modules/scenario/types/field-preset'

export function useFieldPresets() {
    const presets = ref<ScenarioFieldPreset[]>([])
    const loading = ref(false)
    const loadError = ref<string | null>(null)
    let requestSequence = 0

    const formToast = useFormToast({
        created: 'Пользовательское поле сохранено',
        updated: 'Пользовательское поле обновлено',
        deleted: 'Пользовательское поле удалено',
    })

    async function load(): Promise<void> {
        const request = ++requestSequence
        loading.value = true
        loadError.value = null

        try {
            const result = await fieldPresetRepository.list()
            if (request === requestSequence) presets.value = result
        } catch (error: unknown) {
            if (request === requestSequence) {
                loadError.value = error instanceof Error ? error.message : 'Не удалось загрузить пользовательские поля.'
            }
        } finally {
            if (request === requestSequence) loading.value = false
        }
    }

    async function save(name: string, field: BlockField, preset: ScenarioFieldPreset | null): Promise<void> {
        requestSequence++
        loading.value = false
        const payload = {name, field}

        if (preset) {
            const updated = await fieldPresetRepository.update(preset.id, payload)
            presets.value = presets.value
                .map((item) => item.id === preset.id ? updated : item)
                .sort((left, right) => left.name.localeCompare(right.name, 'ru'))
            formToast.saved(true)
            return
        }

        const created = await fieldPresetRepository.create(payload)
        presets.value = [...presets.value, created]
            .sort((left, right) => left.name.localeCompare(right.name, 'ru'))
        formToast.saved(false)
    }

    async function remove(preset: ScenarioFieldPreset): Promise<void> {
        requestSequence++
        loading.value = false
        await fieldPresetRepository.remove(preset.id)
        presets.value = presets.value.filter((item) => item.id !== preset.id)
        formToast.deleted()
    }

    return {presets, loading, loadError, load, save, remove, formToast}
}
