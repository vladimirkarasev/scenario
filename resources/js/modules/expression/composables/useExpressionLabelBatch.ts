import {ref} from 'vue'
import {useLatestRequest} from '@/composables/useLatestRequest'
import {expressionRepository} from '@/modules/expression/repositories/expressionRepository'
import type {ExpressionContext, ExpressionRepository} from '@/modules/expression/types/expression'

export interface ExpressionLabelSource {
    id: number | string
    data: ExpressionContext
}

export function useExpressionLabelBatch(
    repository: ExpressionRepository = expressionRepository,
) {
    const labels = ref<Record<string, string>>({})
    const {loading, error, execute, cancel} = useLatestRequest('Не удалось сформировать подписи.')

    async function load(
        template: string,
        sources: ExpressionLabelSource[],
        sharedContext: ExpressionContext = {},
    ): Promise<void> {
        if (template.trim() === '' || sources.length === 0) {
            clear()
            return
        }

        const contexts = sources.map((source) => ({
            id: source.id,
            data: {...sharedContext, ...source.data},
        }))
        const result = await execute(() => repository.renderBatch(template, contexts))

        if (result) {
            labels.value = result
        }
    }

    function label(id: number | string): string {
        return labels.value[String(id)] ?? ''
    }

    function clear(): void {
        cancel()
        labels.value = {}
        error.value = null
    }

    return {labels, loading, error, load, label, clear}
}
