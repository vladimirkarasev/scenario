<?php

declare(strict_types=1);

namespace App\Services\Expression\Functions;

use App\Services\Expression\ExpressionFunctionInterface;
use App\Services\Expression\Support\CarbonParser;
use App\Services\Expression\Support\MomentFormat;
use Throwable;

final readonly class DateFormatFunction implements ExpressionFunctionInterface
{
    public function name(): string
    {
        return 'dateFormat';
    }

    public function evaluate(array $context, mixed ...$args): string
    {
        $dt = CarbonParser::parse($args[0] ?? null);
        $format = $args[1] ?? null;

        if ($dt === null || ! is_string($format)) {
            return '';
        }

        try {
            return $dt->format(MomentFormat::toPhp($format));
        } catch (Throwable) {
            return '';
        }
    }
}
