<?php

declare(strict_types=1);

namespace App\Services\Expression\Support;

final readonly class MomentFormat
{
    public static function toPhp(string $format): string
    {
        return strtr($format, [
            'YYYY' => 'Y',
            'YY' => 'y',
            'MM' => 'm',
            'DD' => 'd',
            'HH' => 'H',
            'mm' => 'i',
            'ss' => 's',
        ]);
    }
}
