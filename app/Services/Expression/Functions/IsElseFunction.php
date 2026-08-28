<?php

declare(strict_types=1);

namespace App\Services\Expression\Functions;

use App\Services\Expression\ExpressionFunctionInterface;

final readonly class IsElseFunction implements ExpressionFunctionInterface
{
    public function name(): string
    {
        return 'isElse';
    }

    public function evaluate(array $context, mixed ...$args): bool
    {
        return true;
    }

    public static function matches(string $expression): bool
    {
        $expression = trim($expression);

        return preg_match('/^isElse\s*\(\s*\)$/u', $expression) === 1
            || preg_match('/^\{\{\s*isElse\s*\(\s*\)\s*\}\}$/u', $expression) === 1
            || preg_match('/^\$\{\s*isElse\s*\(\s*\)\s*\}$/u', $expression) === 1;
    }
}
