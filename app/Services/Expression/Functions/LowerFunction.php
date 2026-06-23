<?php

declare(strict_types=1);

namespace App\Services\Expression\Functions;

use App\Services\Expression\ExpressionFunctionInterface;
use App\Services\Expression\Support\Scalar;

final readonly class LowerFunction implements ExpressionFunctionInterface
{
    public function name(): string
    {
        return 'lower';
    }

    public function evaluate(array $context, mixed ...$args): string
    {
        return mb_strtolower(Scalar::toString($args[0] ?? null));
    }
}
