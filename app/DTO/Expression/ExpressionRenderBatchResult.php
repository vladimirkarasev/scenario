<?php

declare(strict_types=1);

namespace App\DTO\Expression;

final readonly class ExpressionRenderBatchResult
{
    /** @param array<string, string> $values */
    public function __construct(
        public array $values,
    ) {
    }

    /** @return object */
    public function toObject(): object
    {
        return (object) $this->values;
    }
}
