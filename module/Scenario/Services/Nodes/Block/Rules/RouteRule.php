<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes\Block\Rules;

use Illuminate\Contracts\Validation\ValidationRule;

final readonly class RouteRule implements ValidationRule
{
    public bool $implicit;

    public function __construct(private bool $required)
    {
        $this->implicit = true;
    }

    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        $waypoints = is_array($value) ? ($value['waypoints'] ?? null) : null;
        $validCount = is_array($waypoints) ? count(array_filter($waypoints, $this->isValidWaypoint(...))) : 0;

        if ($validCount < 2) {
            if ($this->required) {
                $fail('Постройте маршрут минимум из двух точек.');
            }

            return;
        }
    }

    private function isValidWaypoint(mixed $waypoint): bool
    {
        return is_array($waypoint)
            && isset($waypoint['lat'], $waypoint['lng'])
            && is_numeric($waypoint['lat'])
            && is_numeric($waypoint['lng']);
    }
}
