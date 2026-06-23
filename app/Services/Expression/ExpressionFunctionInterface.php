<?php

declare(strict_types=1);

namespace App\Services\Expression;

interface ExpressionFunctionInterface
{
    public function name(): string;

    /** @param  array<mixed>  $context */
    public function evaluate(array $context, mixed ...$args): mixed;
}
