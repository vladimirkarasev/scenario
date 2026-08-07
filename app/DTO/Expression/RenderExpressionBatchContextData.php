<?php

declare(strict_types=1);

namespace App\DTO\Expression;

final readonly class RenderExpressionBatchContextData
{
    public function __construct(
        public int|string $id,
        public ExpressionContextData $context,
    ) {
    }
}
