<?php

declare(strict_types=1);

namespace App\DTO\Expression;

final readonly class ExpressionRenderResult
{
    public function __construct(
        public string $rendered,
    ) {
    }

    /** @return array{rendered: string} */
    public function toArray(): array
    {
        return ['rendered' => $this->rendered];
    }
}
