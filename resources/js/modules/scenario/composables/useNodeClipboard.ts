import {
    parseScenarioFlowClipboard,
    serializeScenarioFlowClipboard,
    type ScenarioBlock,
    type ScenarioConnection,
} from '@/modules/scenario/lib/scenario-flow-document'
import {
    copyText,
    DEFAULT_COPY_STRATEGY,
    readText,
    rememberCopiedText,
    type CopyStrategy,
} from '@/lib/clipboard'

export const NODE_CLIPBOARD_STRATEGY: CopyStrategy = DEFAULT_COPY_STRATEGY

let fallbackClipboard: string | null = null

export function useNodeClipboard(strategy: CopyStrategy = NODE_CLIPBOARD_STRATEGY) {
    function serialize(blocks: ScenarioBlock[], connections: ScenarioConnection[]): string {
        const payload = serializeScenarioFlowClipboard(blocks, connections)
        fallbackClipboard = payload
        rememberCopiedText(payload)
        return payload
    }

    async function copyToClipboard(blocks: ScenarioBlock[], connections: ScenarioConnection[]): Promise<boolean> {
        return copyText(serialize(blocks, connections), strategy)
    }

    function rememberClipboard(blocks: ScenarioBlock[], connections: ScenarioConnection[]): void {
        serialize(blocks, connections)
    }

    async function readFromClipboard(): Promise<{ blocks: ScenarioBlock[]; connections: ScenarioConnection[] } | null> {
        const text = await readText(strategy)
        const systemPayload = text ? parseScenarioFlowClipboard(text) : null
        return systemPayload ?? (fallbackClipboard ? parseScenarioFlowClipboard(fallbackClipboard) : null)
    }

    return {copyToClipboard, readFromClipboard, rememberClipboard}
}
