<?php

declare(strict_types=1);

namespace App\Services\Expression\Functions;

use App\Services\Expression\ExpressionFunctionInterface;
use App\Services\Expression\Support\CarbonParser;
use App\Services\Expression\Support\Duration;

final readonly class AddTimeFunction implements ExpressionFunctionInterface
{
    public function name(): string
    {
        return 'addTime';
    }

    public function evaluate(array $context, mixed ...$args): string
    {
        $dt = CarbonParser::parse($args[0] ?? null);
        $duration = $args[1] ?? null;

        if ($dt === null || ! is_string($duration) || $duration === '') {
            return '';
        }

        foreach (Duration::parse($duration) as $unit => $qty) {
            $dt = match ($unit) {
                'y' => $dt->addYears($qty),
                'mo' => $dt->addMonths($qty),
                'w' => $dt->addWeeks($qty),
                'd' => $dt->addDays($qty),
                'h' => $dt->addHours($qty),
                'm' => $dt->addMinutes($qty),
                's' => $dt->addSeconds($qty),
                default => $dt,
            };
        }

        return $dt->toIso8601String();
    }
}
