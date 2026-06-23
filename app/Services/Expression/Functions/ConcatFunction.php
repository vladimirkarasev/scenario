<?php

declare(strict_types=1);

namespace App\Services\Expression\Functions;

use App\Services\Expression\ExpressionFunctionInterface;
use App\Services\Expression\Support\Scalar;

final readonly class ConcatFunction implements ExpressionFunctionInterface
{
    public function name(): string
    {
        return 'concat';
    }

    public function evaluate(array $context, mixed ...$args): string
    {
        $out = '';
        foreach ($args as $a) {
            $out .= Scalar::toString($a);
        }

        return $out;
    }
}
