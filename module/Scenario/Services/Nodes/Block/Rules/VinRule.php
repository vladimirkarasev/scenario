<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes\Block\Rules;

use Illuminate\Contracts\Validation\ValidationRule;

final readonly class VinRule implements ValidationRule
{
    /**
     * Run even when the attribute is absent from the input, so a required
     * VIN field is rejected instead of silently skipped.
     */
    public bool $implicit;

    public function __construct(private bool $required)
    {
        $this->implicit = true;
    }

    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        $raw = is_array($value) ? ($value['value'] ?? null) : $value;
        $vin = is_string($raw) ? $raw : '';

        if ($vin === '') {
            if ($this->required) {
                $fail('Поле обязательно для заполнения.');
            }

            return;
        }

        if (! preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $vin)) {
            $fail('Введите корректный VIN: 17 символов, заглавные латинские буквы (без I, O, Q) и цифры.');
        }
    }
}
