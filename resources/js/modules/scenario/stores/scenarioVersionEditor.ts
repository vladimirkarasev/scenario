import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { router } from '@inertiajs/vue3'
import {
    cloneScenarioFlowDocument,
    createEmptyScenarioFlowDocument,
    normalizeScenarioFlowDocument,
    type ScenarioFlowDocument,
} from '@/modules/scenario/lib/scenario-flow-document'
import { destroyJson, getJson, sendJson } from '@/lib/http'
import { clearScenarioVersionDraft, loadScenarioVersionDraft, saveScenarioVersionDraft } from '@/modules/scenario/lib/scenario-version-draft'

interface ScenarioVersion {
    id: string
    name?: string | null
    status?: string | null
    schema_json?: unknown
    created_at?: string | null
    updated_at?: string | null
    revisions?: Array<{ id: number; created_at: string | null }>
}

interface Scenario {
    id: string
    name: string
    versions: ScenarioVersion[]
}

export const useScenarioVersionEditorStore = defineStore('scenarioVersionEditor', () => {
    const scenarioId = ref<string | null>(null)
    const versionId = ref<string | null>(null)
    const loading = ref(true)
    const saving = ref(false)
    const loadError = ref('')
    const canManageCatalog = ref(false)
    const scenarios = ref<Scenario[]>([])
    const versionDocument = ref<ScenarioFlowDocument>(createEmptyScenarioFlowDocument())
    const versionName = ref('')
    const versionStatus = ref('draft')

    const scenario = computed(() => scenarios.value.find((item) => item.id === scenarioId.value) ?? null)
    const versions = computed((): ScenarioVersion[] => scenario.value?.versions ?? [])
    const currentVersion = computed(() => versions.value.find((item) => item.id === versionId.value) ?? null)
    const isCreateMode = computed(() => !versionId.value)
    const displayVersionName = computed(() => versionName.value.trim() || currentVersion.value?.name || 'Untitled version')
    const pageTitle = computed(() => isCreateMode.value ? 'Create Scenario Version' : `Edit ${displayVersionName.value}`)
    const pageDescription = computed(() => scenario.value
        ? `${scenario.value.name}: отдельная страница для создания и редактирования версии схемы.`
        : 'Отдельная страница для создания и редактирования версии схемы.')

    function initialize(nextScenarioId: string, nextVersionId: string | null = null): void {
        scenarioId.value = nextScenarioId
        versionId.value = nextVersionId
    }

    function currentDraftKey() {
        return { scenarioId: scenarioId.value, versionId: versionId.value }
    }

    async function load(): Promise<void> {
        loading.value = true
        loadError.value = ''

        try {
            const payload = await getJson<Record<string, unknown>>('/api/scenarios?active_only=0', 'Failed to load scenario data.')

            canManageCatalog.value = Boolean(payload.canManageCatalog)
            scenarios.value = payload.scenarios as Scenario[] ?? []

            const resolvedScenario = scenarios.value.find((item) => item.id === scenarioId.value) ?? null
            if (!resolvedScenario) throw new Error('Scenario not found.')

            if (versionId.value) {
                const resolvedVersion = resolvedScenario.versions?.find((item: ScenarioVersion) => item.id === versionId.value) ?? null
                if (!resolvedVersion) throw new Error('Scenario version not found.')

                versionName.value = resolvedVersion.name ?? ''
                versionStatus.value = resolvedVersion.status ?? 'draft'
                const persistedDocument = cloneScenarioFlowDocument(normalizeScenarioFlowDocument(resolvedVersion.schema_json ?? {}))
                const draftDocument = loadScenarioVersionDraft(currentDraftKey())

                versionDocument.value = draftDocument.blocks.length || draftDocument.connections.length
                    ? draftDocument
                    : persistedDocument
            } else {
                versionName.value = ''
                versionStatus.value = 'draft'
                versionDocument.value = loadScenarioVersionDraft(currentDraftKey())
            }
        } catch (e: unknown) {
            loadError.value = e instanceof Error ? e.message : String(e)
        } finally {
            loading.value = false
        }
    }

    function persistDraft(): void {
        saveScenarioVersionDraft(currentDraftKey(), versionDocument.value)
    }

    async function save(): Promise<void> {
        if (!scenario.value || !canManageCatalog.value) return

        saving.value = true
        loadError.value = ''

        try {
            const endpoint = versionId.value
                ? `/api/scenario-versions/${versionId.value}`
                : `/api/scenarios/${scenario.value.id}/versions`

            const payload = await sendJson<Record<string, unknown>>(endpoint, {
                method: versionId.value ? 'PUT' : 'POST',
                body: {
                    name: versionName.value.trim() || null,
                    status: versionStatus.value || 'draft',
                    schema_json: normalizeScenarioFlowDocument(versionDocument.value),
                },
                fallbackMessage: versionId.value
                    ? 'Failed to update scenario version.'
                    : 'Failed to create scenario version.',
            })

            clearScenarioVersionDraft(currentDraftKey())
            if (versionId.value) {
                const savedVersion = payload.item as ScenarioVersion
                scenarios.value = scenarios.value.map((item) => item.id === scenario.value!.id
                    ? { ...item, versions: (item.versions ?? []).map((version) => version.id === savedVersion.id ? savedVersion : version) }
                    : item)
                await load()
            } else {
                const savedVersion = payload.item as ScenarioVersion
                router.visit(route('scenario-versions.edit', savedVersion.id))
            }
        } catch (e: unknown) {
            loadError.value = e instanceof Error ? e.message : String(e)
        } finally {
            saving.value = false
        }
    }

    async function remove(version: ScenarioVersion): Promise<void> {
        const versionLabel = version.name?.trim() || version.id
        if (!canManageCatalog.value || !window.confirm(`Delete version ${versionLabel}?`)) return

        saving.value = true
        loadError.value = ''

        try {
            await destroyJson(`/api/scenario-versions/${version.id}`, 'Failed to delete scenario version.')
            router.visit(route('scenarios.edit', scenarioId.value))
        } catch (e: unknown) {
            loadError.value = e instanceof Error ? e.message : String(e)
        } finally {
            saving.value = false
        }
    }

    return {
        scenarioId,
        versionId,
        loading,
        saving,
        loadError,
        canManageCatalog,
        scenarios,
        versionDocument,
        versionName,
        versionStatus,
        scenario,
        versions,
        currentVersion,
        displayVersionName,
        isCreateMode,
        pageTitle,
        pageDescription,
        initialize,
        load,
        persistDraft,
        save,
        remove,
    }
})
