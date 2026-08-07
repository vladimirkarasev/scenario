<?php

declare(strict_types=1);

namespace App\Services\Expression;

use App\DTO\Expression\ExpressionRenderBatchResult;
use App\DTO\Expression\ExpressionRenderResult;
use App\DTO\Expression\RenderExpressionBatchData;
use App\DTO\Expression\RenderExpressionData;

final readonly class ExpressionRenderService
{
    public function __construct(
        private ExpressionService $expressions,
        private ExpressionBatchContextNormalizer $batchContextNormalizer,
    ) {
    }

    public function render(RenderExpressionData $data): ExpressionRenderResult
    {
        return new ExpressionRenderResult(
            rendered: $this->expressions->renderString(
                $data->template,
                $data->context->values,
                emptyExpressionValueAsBlank: true,
            ),
        );
    }

    public function renderBatch(RenderExpressionBatchData $data): ExpressionRenderBatchResult
    {
        $values = [];
        foreach ($this->batchContextNormalizer->normalize($data->contexts) as $context) {
            $values[(string) $context->id] = $this->expressions->renderString(
                $data->template,
                $context->context->values,
                emptyExpressionValueAsBlank: true,
            );
        }

        return new ExpressionRenderBatchResult($values);
    }
}
