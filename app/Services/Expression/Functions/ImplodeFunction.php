<?php

declare(strict_types=1);

namespace App\Services\Expression\Functions;

use App\Services\Expression\ExpressionFunctionInterface;
use App\Services\Expression\Support\Scalar;

final readonly class ImplodeFunction implements ExpressionFunctionInterface
{
    public function name(): string
    {
        return 'implode';
    }

    public function evaluate(array $context, mixed ...$args): string
    {
        $separator = Scalar::toString($args[0] ?? null);
        $items = $args[1] ?? null;

        if (! is_array($items)) {
            return Scalar::toString($items);
        }

        $parts = [];
        foreach ($items as $item) {
            $parts[] = Scalar::toString($item);
        }

        return implode($separator, $parts);
    }
}
