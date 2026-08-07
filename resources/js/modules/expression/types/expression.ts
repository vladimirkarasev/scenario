export type ExpressionContext = Record<string, unknown>

export interface ExpressionRenderResult {
    rendered: string
}

export interface ExpressionRenderResponse {
    data: ExpressionRenderResult
}

export interface ExpressionRenderBatchContext {
    id: number | string
    data: ExpressionContext
}

export type ExpressionRenderBatchResult = Record<string, string>

export interface ExpressionRenderBatchResponse {
    data: ExpressionRenderBatchResult
}

export interface ExpressionRepository {
    render(template: string, context: ExpressionContext): Promise<ExpressionRenderResult>
    renderBatch(template: string, context: ExpressionRenderBatchContext[]): Promise<ExpressionRenderBatchResult>
}
