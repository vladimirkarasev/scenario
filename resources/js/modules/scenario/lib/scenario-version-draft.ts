import {
    cloneScenarioFlowDocument,
    createEmptyScenarioFlowDocument,
    normalizeScenarioFlowDocument,
    type ScenarioFlowDocument,
} from '@/modules/scenario/lib/scenario-flow-document'

interface DraftKeyParams {
    scenarioId?: string | null
    versionId?: string | null
}

function draftKey({scenarioId = null, versionId = null}: DraftKeyParams): string {
    if (versionId) {
        return `scenario-version-draft:version:${versionId}`
    }

    return `scenario-version-draft:scenario:${scenarioId}:new`
}

export function loadScenarioVersionDraft({scenarioId = null, versionId = null}: DraftKeyParams): ScenarioFlowDocument {
    if (typeof window === 'undefined' || (!scenarioId && !versionId)) {
        return createEmptyScenarioFlowDocument()
    }

    const raw = window.sessionStorage.getItem(draftKey({scenarioId, versionId}))

    if (!raw) {
        return createEmptyScenarioFlowDocument()
    }

    try {
        return cloneScenarioFlowDocument(normalizeScenarioFlowDocument(JSON.parse(raw)))
    } catch {
        return createEmptyScenarioFlowDocument()
    }
}

export function saveScenarioVersionDraft({
                                             scenarioId = null,
                                             versionId = null
                                         }: DraftKeyParams, document: unknown): void {
    if (typeof window === 'undefined' || (!scenarioId && !versionId)) {
        return
    }

    window.sessionStorage.setItem(
        draftKey({scenarioId, versionId}),
        JSON.stringify(normalizeScenarioFlowDocument(document)),
    )
}

export function clearScenarioVersionDraft({scenarioId = null, versionId = null}: DraftKeyParams): void {
    if (typeof window === 'undefined' || (!scenarioId && !versionId)) {
        return
    }

    window.sessionStorage.removeItem(draftKey({scenarioId, versionId}))
}
