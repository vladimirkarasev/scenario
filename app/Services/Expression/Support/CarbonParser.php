<?php

declare(strict_types=1);

namespace App\Services\Expression\Support;

use Carbon\Carbon;
use Throwable;

final readonly class CarbonParser
{
    public static function parse(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
