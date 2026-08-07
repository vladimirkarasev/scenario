import {sendJson} from '@/lib/http'
import type {
    ExpressionContext,
    ExpressionRepository,
    ExpressionRenderBatchContext,
    ExpressionRenderBatchResponse,
    ExpressionRenderBatchResult,
    ExpressionRenderResponse,
    ExpressionRenderResult,
} from '@/modules/expression/types/expression'

export const expressionRepository: ExpressionRepository = {
    async render(template: string, context: ExpressionContext): Promise<ExpressionRenderResult> {
        const response = await sendJson<ExpressionRenderResponse>('/api/expression/render', {
            body: {template, context},
            fallbackMessage: 'Не удалось сформировать пример выражения.',
        })

        return response.data
    },

    async renderBatch(
        template: string,
        context: ExpressionRenderBatchContext[],
    ): Promise<ExpressionRenderBatchResult> {
        const response = await sendJson<ExpressionRenderBatchResponse>('/api/expression/render-batch', {
            body: {item: {template}, context},
            fallbackMessage: 'Не удалось сформировать примеры выражений.',
        })

        return response.data
    },
}
