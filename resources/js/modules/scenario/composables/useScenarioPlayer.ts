import {computed, ref} from 'vue'
import type {Subscription} from 'centrifuge'
import type {
    ActionStageStatus,
    ScenarioRunMessage,
    ScenarioRunPayload,
    ScenarioRunStep,
    ScenarioTimelineEntry,
    ScenarioTimelineRow,
} from '@/modules/scenario/lib/scenario-player-types'
import {scenarioRunRepository} from '@/modules/scenario/repositories/scenarioRunRepository'
import {subscribeTo, unsubscribeFrom} from '@/composables/useCentrifugo'
import {HttpValidationError} from '@/lib/http'

interface CreateRunOptions {
    scenarioId: string
    scenarioVersionId?: string | null
    context?: Record<string, unknown>
}

export function useScenarioPlayer() {
    const loading = ref(false)
    const error = ref('')
    const fieldErrors = ref<Record<string, string[]>>({})
    const run = ref<ScenarioRunPayload | null>(null)
    const timeline = ref<ScenarioTimelineEntry[]>([])

    const actionStages = ref<Record<string, ActionStageStatus>>({})
    const pipelineFailed = ref(false)

    let sub: Subscription | null = null
    let subscribedRunId: string | null = null

    const completed = computed(() => run.value?.status === 'completed')
    const failed = computed(() => run.value?.status === 'failed')
    const currentNode = computed(() => run.value?.current_node ?? null)
    const rendered = computed(() => run.value?.rendered ?? null)
    const context = computed(() => run.value?.context ?? {})
    const steps = computed(() => run.value?.steps ?? [])
    const pastTimeline = computed(() => timeline.value.filter((entry) => entry.status === 'past'))
    const currentTimeline = computed(() => timeline.value.find((entry) => entry.status === 'current') ?? null)

    const pastTimelineRows = computed<ScenarioTimelineRow[]>(() => {
        const rows: ScenarioTimelineRow[] = []
        const stack: { versionId: string; name: string; version: string }[] = []
        let seq = 0

        const rootVersionId = run.value?.root_scenario_version_id
        if (rootVersionId) {
            stack.push({
                versionId: rootVersionId,
                name: run.value?.root_scenario_name ?? 'Сценарий',
                version: run.value?.root_scenario_version_name ?? '',
            })
        }

        for (const entry of timeline.value) {
            const versionId = entry.scenario_version_id ?? ''
            const name = entry.scenario_name ?? 'Сценарий'
            const version = entry.scenario_version_name ?? ''

            if (versionId) {
                const top = stack[stack.length - 1]
                if (!top) {
                    stack.push({versionId, name, version})
                } else if (top.versionId !== versionId) {
                    const idx = stack.findIndex((f) => f.versionId === versionId)
                    if (idx >= 0) {
                        while (stack.length - 1 > idx) {
                            const frame = stack.pop()!
                            rows.push({type: 'divider', key: `dv-end-${seq++}`, kind: 'end', scenarioName: frame.name, versionName: frame.version})
                        }
                    } else {
                        stack.push({versionId, name, version})
                        rows.push({type: 'divider', key: `dv-start-${seq++}`, kind: 'start', scenarioName: name, versionName: version})
                    }
                }
            }

            if (entry.status === 'past') {
                rows.push({type: 'entry', entry})
            }
        }

        if ((completed.value || failed.value) && stack.length > 1) {
            for (let i = stack.length - 1; i >= 1; i--) {
                const frame = stack[i]
                rows.push({type: 'divider', key: `dv-end-tail-${i}`, kind: 'end', scenarioName: frame.name, versionName: frame.version})
            }
        }

        return rows
    })

    let isJumping = false

    function cloneValue<T>(value: T): T {
        return JSON.parse(JSON.stringify(value)) as T
    }

    function syncTimeline(nextRun: ScenarioRunPayload | null): void {
        if (!nextRun?.current_node || !nextRun.rendered) {
            return
        }

        const currentNodeId = String(nextRun.current_node_id)
        const updatedEntry = {
            node_id: currentNodeId,
            node_type: String(nextRun.current_node.type),
            rendered: cloneValue(nextRun.rendered),
            context: cloneValue(nextRun.context ?? {}),
            scenario_version_id: nextRun.scenario_version_id ?? null,
            scenario_name: nextRun.current_scenario_name ?? null,
            scenario_version_name: nextRun.scenario_version_name ?? null,
        }

        const stepsById = new Map<string, ScenarioRunStep>(
            (nextRun.steps ?? []).map((s) => [`step:${s.id}`, s]),
        )
        const freshContext = cloneValue(nextRun.context ?? {})
        const refreshed = timeline.value.map((entry) => {
            const step = stepsById.get(entry.key)
            if (!step?.rendered) return entry
            return {
                ...entry,
                rendered: cloneValue(step.rendered),
                context: freshContext,
            }
        })

        const currentStep = (nextRun.steps ?? []).find(
            (step) => step.node_id === nextRun.current_node_id && !step.exited_at,
        )
        const fallbackKey = `${currentNodeId}:${nextRun.steps.length}`
        const entryKey = currentStep?.id ? `step:${currentStep.id}` : fallbackKey

        const existingIdx = refreshed.findIndex((e) => e.key === entryKey)
        if (existingIdx !== -1) {
            timeline.value = [
                ...refreshed.slice(0, existingIdx).map((e) => ({...e, status: 'past' as const})),
                {...refreshed[existingIdx], ...updatedEntry, status: 'current' as const},
            ]
            return
        }

        timeline.value = [
            ...refreshed.map((e) => ({...e, status: 'past' as const})),
            {key: entryKey, ...updatedEntry, status: 'current' as const},
        ]
    }

    const VISIBLE_NODE_TYPES = ['block', 'question', 'condition', 'action']

    function buildTimelineFromSteps(nextRun: ScenarioRunPayload): void {
        const existingKeys = new Set(timeline.value.map((e) => e.key))

        const entries: ScenarioTimelineEntry[] = (nextRun.steps ?? [])
            .slice()
            .reverse()
            .filter(
                (step) =>
                    VISIBLE_NODE_TYPES.includes(step.node_type) &&
                    step.rendered != null &&
                    !existingKeys.has(`step:${step.id}`),
            )
            .map((step) => ({
                key: `step:${step.id}`,
                node_id: step.node_id,
                node_type: step.node_type,
                status: 'past' as const,
                rendered: cloneValue(step.rendered!),
                context: cloneValue(nextRun.context ?? {}),
                scenario_version_id: step.scenario_version_id ?? null,
                scenario_name: step.scenario_name ?? null,
                scenario_version_name: step.scenario_version_name ?? null,
            }))

        if (entries.length) {
            timeline.value = [...entries, ...timeline.value]
        }
    }

    function setRun(nextRun: ScenarioRunPayload | null): void {
        run.value = nextRun
        const isFirstLoad = timeline.value.length === 0

        if (isJumping && nextRun) {
            timeline.value = []
            buildTimelineFromSteps(nextRun)
        }

        syncTimeline(nextRun)
        syncActionPipeline(nextRun)

        if (!isJumping && isFirstLoad && nextRun) {
            buildTimelineFromSteps(nextRun)
        }
    }

    function syncActionPipeline(nextRun: ScenarioRunPayload | null): void {
        const r = nextRun?.rendered as {
            type?: string
            stages?: { code: string }[]
            results?: Record<string, ActionStageStatus>
            failed?: boolean
        } | null

        if (r?.type === 'action') {
            const persisted = r.results ?? {}
            const next: Record<string, ActionStageStatus> = {}
            for (const stage of r.stages ?? []) {
                next[stage.code] = actionStages.value[stage.code] ?? persisted[stage.code] ?? 'pending'
            }
            actionStages.value = next
            pipelineFailed.value = Boolean(r.failed)
            return
        }

        actionStages.value = {}
        pipelineFailed.value = false
    }

    function onMessage(msg: ScenarioRunMessage): void {
        switch (msg.type) {
            case 'run_updated':
                setRun(msg.run)
                break
            case 'action_started':
                actionStages.value = {...actionStages.value, [msg.code]: 'running'}
                break
            case 'action_completed':
                actionStages.value = {...actionStages.value, [msg.code]: 'success'}
                break
            case 'action_failed':
                actionStages.value = {...actionStages.value, [msg.code]: 'failed'}
                pipelineFailed.value = true
                break
        }
    }

    async function subscribe(runId: string): Promise<void> {
        if (subscribedRunId === runId && sub) return
        await unsubscribe()
        subscribedRunId = runId
        try {
            sub = await subscribeTo<ScenarioRunMessage>(`scenario-run:${runId}`, onMessage)
        } catch {
            subscribedRunId = null
        }
    }

    async function unsubscribe(): Promise<void> {
        if (sub) {
            try {
                await unsubscribeFrom(sub)
            } catch { /* ignore */
            }
            sub = null
        }
        subscribedRunId = null
    }

    async function createRun({scenarioId, scenarioVersionId, context: ctx}: CreateRunOptions) {
        loading.value = true
        error.value = ''
        timeline.value = []
        try {
            const payload = await scenarioRunRepository.create({
                scenario_id: scenarioId,
                scenario_version_id: scenarioVersionId ?? null,
                context: ctx ?? {},
            })
            setRun(payload)
        } catch (e: unknown) {
            error.value = e instanceof Error ? e.message : String(e)
        } finally {
            loading.value = false
        }
    }

    async function loadRun(runId: string) {
        loading.value = true
        error.value = ''
        timeline.value = []
        try {
            const payload = await scenarioRunRepository.find(runId)
            setRun(payload)
        } catch (e: unknown) {
            error.value = e instanceof Error ? e.message : String(e)
        } finally {
            loading.value = false
        }
    }

    async function continueRun(input: Record<string, unknown> = {}, selectedTargetNodeId?: string | null) {
        if (!run.value) return
        loading.value = true
        error.value = ''
        fieldErrors.value = {}
        try {
            const payload = await scenarioRunRepository.continue(run.value.id, input, selectedTargetNodeId)
            setRun(payload)
        } catch (e: unknown) {
            if (e instanceof HttpValidationError) {
                fieldErrors.value = e.errors
                error.value = ''
            } else {
                error.value = e instanceof Error ? e.message : String(e)
            }
        } finally {
            loading.value = false
        }
    }

    async function retryAction() {
        if (!run.value) return
        actionStages.value = {}
        pipelineFailed.value = false
        loading.value = true
        error.value = ''
        try {
            const payload = await scenarioRunRepository.retryAction(run.value.id)
            setRun(payload)
        } catch (e: unknown) {
            error.value = e instanceof Error ? e.message : String(e)
        } finally {
            loading.value = false
        }
    }

    async function jumpTo(nodeId: string) {
        if (!run.value) return
        loading.value = true
        error.value = ''
        isJumping = true
        try {
            const payload = await scenarioRunRepository.jump(run.value.id, nodeId)
            setRun(payload)
        } catch (e: unknown) {
            error.value = e instanceof Error ? e.message : String(e)
        } finally {
            isJumping = false
            loading.value = false
        }
    }

    return {
        loading,
        error,
        fieldErrors,
        run,
        currentNode,
        rendered,
        context,
        steps,
        timeline,
        pastTimeline,
        pastTimelineRows,
        currentTimeline,
        completed,
        failed,
        actionStages,
        pipelineFailed,
        createRun,
        loadRun,
        continueRun,
        retryAction,
        jumpTo,
        subscribe,
        unsubscribe,
    }
}
