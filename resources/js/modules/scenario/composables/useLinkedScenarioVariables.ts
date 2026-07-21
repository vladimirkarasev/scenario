import {computed, ref, watch} from 'vue'
import {scenarioVersionRepository} from '@/modules/scenario/repositories/scenarioVersionRepository'
import {normalizeScenarioFlowDocument} from '@/modules/scenario/lib/scenario-flow-document'
import {blocksToVariableEntries} from '@/modules/scenario/lib/scenario-variables'
import type {VariableEntry} from '@/modules/scenario/types/scenario-variable-entry'

export interface LinkedScenarioRef {
    scenarioId: string
    versionId: string | null
    scenarioName: string
}

export interface LinkedBlockEntry {
    id: string
    type: string
    data: { title: string }
}

interface ResolvedLink {
    entries: VariableEntry[]
    blocks: LinkedBlockEntry[]
}

const EMPTY: ResolvedLink = {entries: [], blocks: []}

export function toLinkedScenarioRefs(
    nodes: Array<{ type?: string; data?: Record<string, unknown> }>,
): LinkedScenarioRef[] {
    return nodes
        .filter((node) => node.type === 'scenario_link' && Boolean(node.data?.targetScenarioId))
        .map((node) => ({
            scenarioId: String(node.data?.targetScenarioId),
            versionId: node.data?.targetVersionId ? String(node.data.targetVersionId) : null,
            scenarioName: String(node.data?.targetScenarioName ?? ''),
        }))
}

export function useLinkedScenarioVariables(getLinks: () => LinkedScenarioRef[]) {
    const linkedEntries = ref<VariableEntry[]>([])
    const linkedBlocks = ref<LinkedBlockEntry[]>([])
    const cache = new Map<string, ResolvedLink>()

    function keyFor(link: LinkedScenarioRef): string {
        return `${link.scenarioId}::${link.versionId ?? 'active'}`
    }

    async function resolveVersionId(link: LinkedScenarioRef): Promise<string | null> {
        if (link.versionId) return link.versionId
        const versions = await scenarioVersionRepository.list(link.scenarioId)
        const active = versions
            .filter((v) => v.status === 'active')
            .sort((a, b) => (b.created_at ?? '').localeCompare(a.created_at ?? ''))[0]
        return active?.id ?? versions[0]?.id ?? null
    }

    async function resolve(link: LinkedScenarioRef): Promise<ResolvedLink> {
        const key = keyFor(link)
        const cached = cache.get(key)
        if (cached) return cached

        try {
            const versionId = await resolveVersionId(link)
            if (!versionId) {
                cache.set(key, EMPTY)
                return EMPTY
            }
            const version = await scenarioVersionRepository.find(link.scenarioId, versionId)
            const doc = normalizeScenarioFlowDocument(version.schema_json)
            const prefix = link.scenarioName || 'Связанный сценарий'

            const entries: VariableEntry[] = blocksToVariableEntries(doc.blocks).map((entry) => ({
                ...entry,
                blockId: `link:${link.scenarioId}:${entry.blockId}`,
                blockTitle: `↪ ${prefix} · ${entry.blockTitle}`,
                isCurrent: false,
            }))

            const blocks: LinkedBlockEntry[] = [
                ...new Map(
                    entries.map((e) => [e.blockId, {id: e.blockId, type: 'block', data: {title: e.blockTitle}}]),
                ).values(),
            ]

            const result: ResolvedLink = {entries, blocks}
            cache.set(key, result)
            return result
        } catch {
            cache.set(key, EMPTY)
            return EMPTY
        }
    }

    async function reload(): Promise<void> {
        const links = getLinks().filter((l) => l.scenarioId)
        const results = await Promise.all(links.map(resolve))

        const entries: VariableEntry[] = []
        const blocks: LinkedBlockEntry[] = []
        const seenBlock = new Set<string>()

        for (const result of results) {
            entries.push(...result.entries)
            for (const block of result.blocks) {
                if (seenBlock.has(block.id)) continue
                seenBlock.add(block.id)
                blocks.push(block)
            }
        }

        linkedEntries.value = entries
        linkedBlocks.value = blocks
    }

    const signature = computed(() =>
        getLinks()
            .map((link) => `${link.scenarioId}::${link.versionId ?? 'active'}`)
            .sort()
            .join('|'),
    )

    watch(signature, () => void reload(), {immediate: true})

    return {linkedEntries, linkedBlocks}
}
