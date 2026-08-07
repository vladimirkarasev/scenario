<?php

declare(strict_types=1);

namespace App\Rules\Expression;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class ExpressionContextIdRule implements ValidationRule
{
    private const array RESERVED_IDS = ['__proto__', 'constructor', 'prototype'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (
            (! is_int($value) && ! is_string($value))
            || (is_string($value) && (trim($value) === '' || mb_strlen($value) > 128))
            || (is_string($value) && in_array(mb_strtolower($value), self::RESERVED_IDS, true))
        ) {
            $fail('Идентификатор контекста должен быть целым числом или строкой до 128 символов.');
        }
    }
}
