<?php

declare(strict_types=1);

namespace Module\Scenario\Support;

/**
 * @phpstan-type SelectArray array{value: string, label: string}
 */
final readonly class SelectShape
{
    /**
     * @phpstan-assert-if-true SelectArray $value
     */
    public static function matches(mixed $value): bool
    {
        return is_array($value)
            && isset($value['value'], $value['label'])
            && is_string($value['value'])
            && is_string($value['label']);
    }

    public static function value(mixed $value): ?string
    {
        return self::matches($value) ? $value['value'] : null;
    }

    public static function label(mixed $value): ?string
    {
        return self::matches($value) ? $value['label'] : null;
    }
}
