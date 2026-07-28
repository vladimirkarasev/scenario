import {computed, ref} from 'vue'
import {defineStore} from 'pinia'
import {
    createScenarioBlockField,
    type BlockField,
    type BlockFieldType
} from '@/modules/scenario/lib/scenario-block-fields'
import {
    cloneScenarioFlowDocument,
    normalizeScenarioFlowDocument,
    type ScenarioBlock,
    type ScenarioFlowDocument
} from '@/modules/scenario/lib/scenario-flow-document'
import {loadScenarioVersionDraft} from '@/modules/scenario/lib/scenario-version-draft'

export const useScenarioBlockEditorStore = defineStore('scenarioBlockEditor', () => {
    const scenarioId = ref<string | null>(null)
    const versionId = ref<string | null>(null)
    const blockId = ref('')
    const loading = ref(true)
    const saving = ref(false)
    const loadError = ref('')
    const canManageCatalog = ref(false)
    const versionDocument = ref<ScenarioFlowDocument>(normalizeScenarioFlowDocument({}))
    const blockDraft = ref<ScenarioBlock | null>(null)

    const currentBlock = computed(() => versionDocument.value.blocks.find((item) => item.id === blockId.value) ?? null)
    const pageTitle = computed(() => blockDraft.value
        ? `Edit Step: ${blockDraft.value.data.title || 'Block'}`
        : 'Edit Step')
    const pageDescription = computed(() => 'Отдельная страница редактирования шага и его полей.')

    function initialize(nextScenarioId: string, nextVersionId: string | null = null, nextBlockId: string): void {
        scenarioId.value = nextScenarioId
        versionId.value = nextVersionId
        blockId.value = nextBlockId
    }

    function currentDraftKey() {
        return {scenarioId: scenarioId.value, versionId: versionId.value}
    }

    function hydrateBlockDraft(): void {
        if (!currentBlock.value || currentBlock.value.type !== 'block') {
            throw new Error('Step block not found.')
        }

        blockDraft.value = cloneScenarioFlowDocument({
            ...versionDocument.value,
            blocks: [currentBlock.value],
            connections: [],
        }).blocks[0]
    }

    async function load(): Promise<void> {
        loading.value = true
        loadError.value = ''

        try {
            canManageCatalog.value = true

            const draftDocument = loadScenarioVersionDraft(currentDraftKey())

            if (draftDocument.blocks.length || draftDocument.connections.length) {
                versionDocument.value = draftDocument
            } else {
                versionDocument.value = normalizeScenarioFlowDocument({
                    format: 'scenario-flow',
                    version: 1,
                    viewport: {x: 0, y: 0, zoom: 1},
                    blocks: [{
                        id: blockId.value,
                        type: 'block',
                        position: {x: 0, y: 0},
                        data: {
                            title: 'Блок',
                            variable: blockId.value,
                            skipInSurvey: false,
                            text: '',
                            fields: [],
                            targetScenarioId: null,
                            conditionBranches: [],
                        },
                    }],
                    connections: [],
                })
            }

            hydrateBlockDraft()
        } catch (e: unknown) {
            loadError.value = e instanceof Error ? e.message : String(e)
        } finally {
            loading.value = false
        }
    }

    function syncBlockIntoDocument(): void {
        if (!blockDraft.value) return

        const draft = blockDraft.value
        versionDocument.value = {
            ...versionDocument.value,
            blocks: versionDocument.value.blocks.map((block) =>
                block.id === draft.id ? draft : block,
            ),
        }
    }

    function updateDraft(patch: Partial<ScenarioBlock['data']>): void {
        if (!blockDraft.value) return

        blockDraft.value = {
            ...blockDraft.value,
            data: {...blockDraft.value.data, ...patch},
        }
    }

    function updateField(fieldId: string, nextField: BlockField): void {
        if (!blockDraft.value) return

        blockDraft.value = {
            ...blockDraft.value,
            data: {
                ...blockDraft.value.data,
                fields: blockDraft.value.data.fields.map((field) =>
                    field.id === fieldId ? nextField : field,
                ),
            },
        }
    }

    function ensureUniqueVarName(varName: string, fields: BlockField[], excludeId?: string): string {
        if (!varName) return varName
        const others = fields.filter((f) => f.id !== excludeId)
        const taken = (v: string) => others.some((f) => f.varName === v)
        if (!taken(varName)) return varName
        return `${varName}`
    }

    function createField(type: BlockFieldType): BlockField {
        const existingFields = blockDraft.value?.data.fields ?? []
        const newField = createScenarioBlockField(type, existingFields.length)
        newField.varName = ensureUniqueVarName(newField.varName, existingFields)

        return newField
    }

    function addField(type: BlockFieldType): BlockField | null {
        if (!blockDraft.value) return null

        const newField = createField(type)

        blockDraft.value = {
            ...blockDraft.value,
            data: {
                ...blockDraft.value.data,
                fields: [...blockDraft.value.data.fields, newField],
            },
        }

        return newField
    }

    function removeField(fieldId: string): void {
        if (!blockDraft.value) return

        blockDraft.value = {
            ...blockDraft.value,
            data: {
                ...blockDraft.value.data,
                fields: blockDraft.value.data.fields.filter((field) => field.id !== fieldId),
            },
        }
    }

    function moveField(fieldId: string, direction: 'up' | 'down'): void {
        if (!blockDraft.value) return

        const fields = [...blockDraft.value.data.fields]
        const index = fields.findIndex((field) => field.id === fieldId)
        if (index === -1) return

        const targetIndex = direction === 'up' ? index - 1 : index + 1
        if (targetIndex < 0 || targetIndex >= fields.length) return

        const [field] = fields.splice(index, 1)
        fields.splice(targetIndex, 0, field)

        blockDraft.value = {...blockDraft.value, data: {...blockDraft.value.data, fields}}
    }

    function reorderFields(orderedIds: string[]): void {
        if (!blockDraft.value) return

        const fields = blockDraft.value.data.fields
        const byId = new Map(fields.map((field) => [field.id, field]))
        const reordered = orderedIds.map((id) => byId.get(id)).filter((f): f is BlockField => Boolean(f))
        if (reordered.length !== fields.length) return

        blockDraft.value = {...blockDraft.value, data: {...blockDraft.value.data, fields: reordered}}
    }

    function moveFieldToIndex(fieldId: string, targetIndex: number): void {
        if (!blockDraft.value) return

        const fields = [...blockDraft.value.data.fields]
        const sourceIndex = fields.findIndex((field) => field.id === fieldId)

        if (sourceIndex === -1 || targetIndex < 0 || targetIndex >= fields.length || sourceIndex === targetIndex) return

        const [field] = fields.splice(sourceIndex, 1)
        fields.splice(targetIndex, 0, field)

        blockDraft.value = {...blockDraft.value, data: {...blockDraft.value.data, fields}}
    }

    async function save(onSaved?: (newVersionId: string) => void): Promise<void> {
        if (!blockDraft.value || !canManageCatalog.value) return

        saving.value = true
        syncBlockIntoDocument()
        await new Promise(r => setTimeout(r, 350))
        onSaved?.(versionId.value ?? blockDraft.value.id)
        saving.value = false
    }

    function resetDraft(): void {
        hydrateBlockDraft()
    }

    return {
        loading,
        saving,
        loadError,
        canManageCatalog,
        versionDocument,
        blockDraft,
        blockId,
        pageTitle,
        pageDescription,
        initialize,
        load,
        syncBlockIntoDocument,
        updateBlockData: updateDraft,
        updateField,
        addField,
        removeField,
        moveField,
        moveFieldToIndex,
        reorderFields,
        save,
        resetDraft,
    }
})
