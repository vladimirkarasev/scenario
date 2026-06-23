<?php

declare(strict_types=1);

namespace Module\Scenario\Support;

/**
 * Структура значения телефонного поля, которое фронт отправляет на сабмите
 * (см. resources/js/components/ui/phone-input/PhoneInput.vue):
 *   - country:   ISO-код страны (например, "RU")
 *   - formatted: маскированное представление ("+7 (999) 999-99-99")
 *   - original:  только цифры ("79999999999")
 *
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

    /**
     * Возвращает formatted, если значение в форме PhoneShape; иначе null.
     */
    public static function formatted(mixed $value): ?string
    {
        return self::matches($value) ? $value['formatted'] : null;
    }

    /**
     * Возвращает original (цифры), если значение в форме PhoneShape; иначе null.
     */
    public static function original(mixed $value): ?string
    {
        return self::matches($value) ? $value['original'] : null;
    }

    /**
     * Возвращает country (ISO-код), если значение в форме PhoneShape; иначе null.
     */
    public static function country(mixed $value): ?string
    {
        return self::matches($value) ? $value['country'] : null;
    }
}
