import { computed, ref } from 'vue'
import type { ScenarioRunPayload, ScenarioRunStep, ScenarioTimelineEntry } from '@/modules/scenario/lib/scenario-player-types'
import { scenarioRunRepository } from '@/modules/scenario/repositories/scenarioRunRepository'
import { HttpValidationError } from '@/lib/http'

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

    const completed = computed(() => run.value?.status === 'completed')
    const failed = computed(() => run.value?.status === 'failed')
    const currentNode = computed(() => run.value?.current_node ?? null)
    const rendered = computed(() => run.value?.rendered ?? null)
    const context = computed(() => run.value?.context ?? {})
    const steps = computed(() => run.value?.steps ?? [])
    const pastTimeline = computed(() => timeline.value.filter((entry) => entry.status === 'past'))
    const currentTimeline = computed(() => timeline.value.find((entry) => entry.status === 'current') ?? null)

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
        }

        // Серверный payload содержит свежий rendered для каждого пройденного шага
        // (перерисован с актуальным контекстом). Обновляем все entries, чтобы
        // отображаемые значения соответствовали текущему состоянию — включая те,
        // что сейчас перейдут из current в past.
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
                ...refreshed.slice(0, existingIdx).map((e) => ({ ...e, status: 'past' as const })),
                { ...refreshed[existingIdx], ...updatedEntry, status: 'current' as const },
            ]
            return
        }

        timeline.value = [
            ...refreshed.map((e) => ({ ...e, status: 'past' as const })),
            { key: entryKey, ...updatedEntry, status: 'current' as const },
        ]
    }

    const VISIBLE_NODE_TYPES = ['block', 'condition']

    function buildTimelineFromSteps(nextRun: ScenarioRunPayload): void {
        const existingKeys = new Set(timeline.value.map((e) => e.key))

        // Backend отдаёт steps в порядке от новых к старым (->latest('id')).
        // Для timeline нужен хронологический порядок: от старых к новым.
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
            }))

        if (entries.length) {
            timeline.value = [...entries, ...timeline.value]
        }
    }

    function setRun(nextRun: ScenarioRunPayload | null): void {
        run.value = nextRun
        const isFirstLoad = timeline.value.length === 0
        syncTimeline(nextRun)

        // При первой загрузке (resume активного run-а, completed/failed) — восстанавливаем историю шагов
        if (!isJumping && isFirstLoad && nextRun) {
            buildTimelineFromSteps(nextRun)
        }
    }

    function unsubscribe(): void {
        // placeholder for future WebSocket unsubscribe
    }

    async function createRun({ scenarioId, scenarioVersionId, context: ctx }: CreateRunOptions) {
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
        currentTimeline,
        completed,
        failed,
        createRun,
        loadRun,
        continueRun,
        jumpTo,
        unsubscribe,
    }
}
