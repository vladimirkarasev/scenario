<?php

declare(strict_types=1);

namespace App\Rules\Expression;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class ExpressionContextRule implements ValidationRule
{
    public function __construct(
        private int $maxBytes = 32_768,
        private int $maxDepth = 8,
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        if ($value !== [] && array_any(array_keys($value), static fn (mixed $key): bool => ! is_string($key))) {
            $fail('Контекст должен быть объектом с именованными ключами.');
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false || strlen($encoded) > $this->maxBytes) {
            $fail('Размер контекста не должен превышать 32 КБ.');
        }

        if ($this->depth($value) > $this->maxDepth) {
            $fail('Глубина контекста не должна превышать 8 уровней.');
        }
    }

    private function depth(mixed $value, int $current = 0): int
    {
        if (! is_array($value) || $value === []) {
            return $current;
        }

        $depth = $current;
        foreach ($value as $item) {
            $depth = max($depth, $this->depth($item, $current + 1));
        }

        return $depth;
    }
}
