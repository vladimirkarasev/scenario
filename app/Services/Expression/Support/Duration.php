<?php

declare(strict_types=1);

namespace App\Services\Expression\Support;

final readonly class Duration
{
    /** @return array<string, int> */
    public static function parse(string $spec): array
    {
        preg_match_all('/(-?\d+)\s*(mo|y|w|d|h|m|s)/i', $spec, $matches, PREG_SET_ORDER);

        $result = [];
        foreach ($matches as $m) {
            $unit = strtolower($m[2]);
            $result[$unit] = ($result[$unit] ?? 0) + (int)$m[1];
        }

        return $result;
    }
}
