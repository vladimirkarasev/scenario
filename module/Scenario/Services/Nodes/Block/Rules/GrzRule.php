<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes\Block\Rules;

use Illuminate\Contracts\Validation\ValidationRule;

final readonly class GrzRule implements ValidationRule
{
    /**
     * Run even when the attribute is absent from the input, so a required
     * GRZ field is rejected instead of silently skipped.
     */
    public bool $implicit;

    public function __construct(private bool $required)
    {
        $this->implicit = true;
    }

    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        $raw = is_array($value) ? ($value['original'] ?? null) : $value;
        $grz = is_string($raw) ? $raw : '';

        if ($grz === '') {
            if ($this->required) {
                $fail('Поле обязательно для заполнения.');
            }

            return;
        }

        if (! preg_match('/^[A-ZА-Я0-9]+$/u', $grz)) {
            $fail('Номер ГРЗ должен быть в верхнем регистре (буквы и цифры).');
        }
    }
}
