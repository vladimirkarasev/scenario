<?php

declare(strict_types=1);

namespace Module\Categories\DTO;

use Illuminate\Http\Request;

final readonly class CategoryData
{
    /**
     * @param  list<string>  $groupIds
     */
    public function __construct(
        public ?string $parentId,
        public string $name,
        public bool $isActive,
        public ?int $actorId,
        public bool $canManageCatalog,
        public array $groupIds = [],
        public bool $inheritToDescendants = false,
    ) {
    }

    public static function fromRequest(Request $request, ?bool $canManageCatalog = null): self
    {
        $rawGroupIds = $request->input('group_ids');
        $groupIds = is_array($rawGroupIds)
            ? array_values(
                array_unique(
                    array_filter(
                        array_map(static fn(mixed $i): string => is_scalar($i) ? (string)$i : '', $rawGroupIds),
                        static fn(string $v): bool => $v !== '',
                    )
                )
            )
            : [];

        return new self(
            parentId: $request->filled('parent_id') ? $request->str('parent_id')->toString() : null,
            name: $request->str('name')->toString(),
            isActive: (bool)$request->input('is_active'),
            actorId: $request->user()?->id,
            canManageCatalog: $canManageCatalog ?? (bool)$request->user()?->can('category_create'),
            groupIds: $groupIds,
            inheritToDescendants: (bool)$request->input('inherit_to_descendants'),
        );
    }

    /** @return array<string, mixed> */
    public function toAttributes(): array
    {
        return [
            'parent_id' => $this->parentId,
            'name' => $this->name,
            'is_active' => $this->isActive,
        ];
    }
}
