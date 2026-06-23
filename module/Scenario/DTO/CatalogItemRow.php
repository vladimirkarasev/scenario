<?php

declare(strict_types=1);

namespace Module\Scenario\DTO;

final readonly class CatalogItemRow
{
    public function __construct(
        public string $item_type,
        public ?string $category_id,
        public ?string $scenario_id,
        public string $name,
        public bool $is_active,
        public ?string $active_version_id,
        public ?string $parent_id,
        public string $path,
        public int $child_count,
        public int $scenario_count,
        public int $version_count,
    ) {
    }

    public static function fromRow(object $row): self
    {
        return new self(
            item_type: self::str($row, 'item_type'),
            category_id: self::nullableStr($row, 'category_id'),
            scenario_id: self::nullableStr($row, 'scenario_id'),
            name: self::str($row, 'name'),
            is_active: self::bool($row, 'is_active'),
            active_version_id: self::nullableStr($row, 'active_version_id'),
            parent_id: self::nullableStr($row, 'parent_id'),
            path: self::str($row, 'path'),
            child_count: self::int($row, 'child_count'),
            scenario_count: self::int($row, 'scenario_count'),
            version_count: self::int($row, 'version_count'),
        );
    }

    private static function str(object $row, string $key): string
    {
        $value = $row->{$key} ?? null;

        return is_scalar($value) ? (string)$value : '';
    }

    private static function nullableStr(object $row, string $key): ?string
    {
        $value = $row->{$key} ?? null;

        if ($value === null) {
            return null;
        }

        return is_scalar($value) ? (string)$value : null;
    }

    private static function bool(object $row, string $key): bool
    {
        return (bool)($row->{$key} ?? false);
    }

    private static function int(object $row, string $key): int
    {
        $value = $row->{$key} ?? 0;

        return is_scalar($value) ? (int)$value : 0;
    }
}
