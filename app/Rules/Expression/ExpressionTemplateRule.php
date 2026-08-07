<?php

declare(strict_types=1);

namespace App\Rules\Expression;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class ExpressionTemplateRule implements ValidationRule
{
    public function __construct(
        private int $maxExpressions = 50,
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $expressionCount = substr_count($value, '{{') + substr_count($value, '${');
        if ($expressionCount > $this->maxExpressions) {
            $fail('Шаблон не должен содержать больше 50 выражений.');
        }
    }
}
