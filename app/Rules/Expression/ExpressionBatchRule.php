<?php

declare(strict_types=1);

namespace App\Rules\Expression;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class ExpressionBatchRule implements ValidationRule
{
    public function __construct(
        private int $maxBytes = 262_144,
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        if (! array_is_list($value)) {
            $fail('Batch-контекст должен быть списком.');
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false || strlen($encoded) > $this->maxBytes) {
            $fail('Размер batch-контекста не должен превышать 256 КБ.');
        }
    }
}
