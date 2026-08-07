import {ref} from 'vue'
import {useLatestRequest} from '@/composables/useLatestRequest'
import {expressionRepository} from '@/modules/expression/repositories/expressionRepository'
import type {ExpressionRepository} from '@/modules/expression/types/expression'
import {
    extractVarName,
    implodeRef,
    type VarLike,
} from '@/modules/scenario/lib/scenario-variable-hints'

export function useSelectExpressionPreview(
    repository: ExpressionRepository = expressionRepository,
) {
    const preview = ref('')
    const {loading, error, execute, cancel} = useLatestRequest('Не удалось сформировать пример выражения.')

    async function load(variable: VarLike): Promise<void> {
        preview.value = ''

        const variableName = extractVarName(variable.varRef)
        const labels = (variable.options ?? [])
            .slice(0, 3)
            .map((option) => option.label || option.value)

        if (variableName === '' || labels.length === 0) {
            cancel()
            error.value = null
            return
        }

        const result = await execute(() => repository.render(implodeRef(variable), {
            [variableName]: labels,
        }))

        if (result) {
            preview.value = result.rendered
        }
    }

    function clear(): void {
        cancel()
        preview.value = ''
        error.value = null
    }

    return {preview, loading, error, load, clear}
}
