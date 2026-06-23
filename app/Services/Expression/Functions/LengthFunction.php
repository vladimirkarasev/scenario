<?php

declare(strict_types=1);

namespace App\Services\Expression\Functions;

use App\Services\Expression\ExpressionFunctionInterface;
use App\Services\Expression\Support\Scalar;

final readonly class LengthFunction implements ExpressionFunctionInterface
{
    public function name(): string
    {
        return 'length';
    }

    public function evaluate(array $context, mixed ...$args): int
    {
        return mb_strlen(Scalar::toString($args[0] ?? null));
    }
}
