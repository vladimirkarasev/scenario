<?php

declare(strict_types=1);

namespace Module\Scenario\DTO;

use Illuminate\Http\Request;

final readonly class CatalogIndexData
{
    public function __construct(
        public ?string $parentId = null,
        public ?string $query = null,
        public bool $hasParentFilter = false,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $filter = $request->query('filter');
        $filter = is_array($filter) ? $filter : [];

        return new self(
            parentId: self::stringOrNull($filter['parent_id'] ?? null),
            query: self::stringOrNull($filter['q'] ?? null),
            hasParentFilter: array_key_exists('parent_id', $filter),
        );
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
