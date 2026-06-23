<?php

declare(strict_types=1);

namespace App\Services\Expression\Support;

final readonly class Scalar
{
    public static function toString(mixed $v): string
    {
        return match (true) {
            is_string($v) => $v,
            is_int($v), is_float($v) => (string)$v,
            is_bool($v) => $v ? 'true' : 'false',
            default => '',
        };
    }
}
