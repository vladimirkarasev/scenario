import {
    parseScenarioFlowClipboard,
    serializeScenarioFlowClipboard,
    type ScenarioBlock,
    type ScenarioConnection,
} from '@/modules/scenario/lib/scenario-flow-document'

let fallbackClipboard: string | null = null

export function useNodeClipboard() {
    async function copyToClipboard(blocks: ScenarioBlock[], connections: ScenarioConnection[]): Promise<void> {
        const payload = serializeScenarioFlowClipboard(blocks, connections)
        fallbackClipboard = payload

        try {
            await navigator.clipboard?.writeText(payload)
        } catch {
        }
    }

    async function readFromClipboard(): Promise<{ blocks: ScenarioBlock[]; connections: ScenarioConnection[] } | null> {
        try {
            const parsed = parseScenarioFlowClipboard(await navigator.clipboard.readText())
            if (parsed) return parsed
        } catch {
        }

        return fallbackClipboard ? parseScenarioFlowClipboard(fallbackClipboard) : null
    }

    return {copyToClipboard, readFromClipboard}
}
