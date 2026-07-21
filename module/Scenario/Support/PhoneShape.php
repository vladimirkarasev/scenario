<?php

declare(strict_types=1);

namespace Module\Scenario\Support;

/**
 * @phpstan-type PhoneArray array{country: string, formatted: string, original: string}
 */
final readonly class PhoneShape
{
    /**
     * @phpstan-assert-if-true PhoneArray $value
     */
    public static function matches(mixed $value): bool
    {
        return is_array($value)
            && isset($value['country'], $value['formatted'], $value['original'])
            && is_string($value['country'])
            && is_string($value['formatted'])
            && is_string($value['original']);
    }

    public static function formatted(mixed $value): ?string
    {
        return self::matches($value) ? $value['formatted'] : null;
    }

    public static function original(mixed $value): ?string
    {
        return self::matches($value) ? $value['original'] : null;
    }

    public static function country(mixed $value): ?string
    {
        return self::matches($value) ? $value['country'] : null;
    }

    public static function national(mixed $value): ?string
    {
        return is_array($value) && isset($value['national']) && is_string($value['national'])
            ? $value['national']
            : null;
    }
}
