<?php

declare(strict_types=1);

namespace Module\Scenario\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class SafeFieldPresetSnapshot implements ValidationRule
{
    private const int MaxBytes = 65_536;
    private const int MaxDepth = 16;
    private const int MaxNodes = 2_000;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_array($value)) {
            return;
        }

        $encoded = json_encode($value);
        if ($encoded === false || strlen($encoded) > self::MaxBytes) {
            $fail('Размер конфигурации поля не должен превышать 64 КБ.');

            return;
        }

        $nodes = 0;
        if (!$this->isSafe($value, 0, $nodes)) {
            $fail('Конфигурация поля слишком сложная или содержит запрещённые ключи.');
        }
    }

    /** @param array<array-key, mixed> $value */
    private function isSafe(array $value, int $depth, int &$nodes): bool
    {
        if ($depth > self::MaxDepth) {
            return false;
        }

        foreach ($value as $key => $item) {
            $nodes++;
            if ($nodes > self::MaxNodes) {
                return false;
            }

            if (is_string($key) && in_array($key, ['__proto__', 'prototype'], true)) {
                return false;
            }

            if (is_array($item) && !$this->isSafe($item, $depth + 1, $nodes)) {
                return false;
            }
        }

        return true;
    }
}
