<?php

declare(strict_types=1);

namespace Module\Scenario\Support;

/**
 * Структура значения поля directory_list, которую фронт отправляет на сабмите
 * (см. resources/js/modules/scenario/components/SurveyDirectoryListField.vue).
 *
 * Для single: один объект DirectoryListShape.
 * Для multiple: массив DirectoryListShape.
 *
 * Поля:
 *   - id:           ID элемента справочника как строка
 *   - label:        отображаемое имя (отрендеренный labelTemplate)
 *   - data:         все поля элемента справочника (Record<string, string|null>)
 *   - parent_id:    ID родителя как строка или null
 *   - external_key: внешний ключ
 *   - other_text:   свободный текст «уточнения» для варианта «Другой» (опционально)
 *
 * @phpstan-type DirectoryListArray array{
 *     id: string,
 *     label: string,
 *     data: array<string, mixed>,
 *     parent_id: string|null,
 *     external_key: string,
 *     other_text?: string|null,
 * }
 */
final readonly class DirectoryListShape
{
    /**
     * Default external_key of the synthetic "Другой" option (mirrors
     * Module\Directories\Services\DirectoryItemService::OTHER_EXTERNAL_KEY).
     */
    public const string OTHER_EXTERNAL_KEY = '__other__';

    /**
     * @phpstan-assert-if-true DirectoryListArray $value
     */
    public static function matches(mixed $value): bool
    {
        return is_array($value)
            && isset($value['id'], $value['label'], $value['data'], $value['external_key'])
            && array_key_exists('parent_id', $value)
            && is_string($value['id'])
            && is_string($value['label'])
            && is_array($value['data'])
            && is_string($value['external_key'])
            && ($value['parent_id'] === null || is_string($value['parent_id']));
    }

    public static function id(mixed $value): ?string
    {
        return self::matches($value) ? $value['id'] : null;
    }

    public static function label(mixed $value): ?string
    {
        return self::matches($value) ? $value['label'] : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function data(mixed $value): ?array
    {
        return self::matches($value) ? $value['data'] : null;
    }

    public static function externalKey(mixed $value): ?string
    {
        return self::matches($value) ? $value['external_key'] : null;
    }

    public static function parentId(mixed $value): ?string
    {
        return self::matches($value) ? $value['parent_id'] : null;
    }

    /**
     * Whether the selected value is the "Другой" fallback option.
     *
     * @param  string  $otherKey  external_key configured on the directory version (defaults to the sentinel)
     */
    public static function isOther(mixed $value, string $otherKey = self::OTHER_EXTERNAL_KEY): bool
    {
        return self::externalKey($value) === $otherKey;
    }

    /**
     * Free-text clarification the respondent typed for the "Другой" option, or null.
     */
    public static function otherText(mixed $value): ?string
    {
        if (!self::matches($value)) {
            return null;
        }

        $text = $value['other_text'] ?? null;

        return is_string($text) && $text !== '' ? $text : null;
    }
}
