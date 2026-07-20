import {
    parseScenarioFlowClipboard,
    serializeScenarioFlowClipboard,
    type ScenarioBlock,
    type ScenarioConnection,
} from '@/modules/scenario/lib/scenario-flow-document'

export function useNodeClipboard() {
    async function copyToClipboard(blocks: ScenarioBlock[], connections: ScenarioConnection[]): Promise<void> {
        try {
            await navigator.clipboard.writeText(serializeScenarioFlowClipboard(blocks, connections))
        } catch {
            // Нет прав на буфер обмена — тихо игнорируем, как остальные copy-to-clipboard в проекте.
        }
    }

    async function readFromClipboard(): Promise<{ blocks: ScenarioBlock[]; connections: ScenarioConnection[] } | null> {
        try {
            return parseScenarioFlowClipboard(await navigator.clipboard.readText())
        } catch {
            return null
        }
    }

    return {copyToClipboard, readFromClipboard}
}
