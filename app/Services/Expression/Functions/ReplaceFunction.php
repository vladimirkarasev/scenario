<?php

declare(strict_types=1);

namespace App\Services\Expression\Functions;

use App\Services\Expression\ExpressionFunctionInterface;
use App\Services\Expression\Support\Scalar;

final readonly class ReplaceFunction implements ExpressionFunctionInterface
{
    public function name(): string
    {
        return 'replace';
    }

    public function evaluate(array $context, mixed ...$args): string
    {
        return str_replace(
            Scalar::toString($args[1] ?? null),
            Scalar::toString($args[2] ?? null),
            Scalar::toString($args[0] ?? null),
        );
    }
}
