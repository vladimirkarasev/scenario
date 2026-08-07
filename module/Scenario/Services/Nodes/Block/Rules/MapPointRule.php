<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes\Block\Rules;

use Illuminate\Contracts\Validation\ValidationRule;

final readonly class MapPointRule implements ValidationRule
{
    public bool $implicit;

    public function __construct(private bool $required)
    {
        $this->implicit = true;
    }

    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        if (! is_array($value) || ! isset($value['lat'], $value['lng']) || ! is_numeric($value['lat']) || ! is_numeric($value['lng'])) {
            if ($this->required) {
                $fail('Укажите точку на карте.');
            }

            return;
        }
    }
}
