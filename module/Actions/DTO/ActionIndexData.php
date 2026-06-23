<?php

declare(strict_types=1);

namespace Module\Actions\DTO;

use Illuminate\Http\Request;

final readonly class ActionIndexData
{
    public function __construct(
        public ?string $search,
        public ?string $type,
        public ?bool $isActive,
        public int $perPage,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $filter = $request->array('filter');
        $pageSize = $request->input('page.size', 20);
        $perPage = is_scalar($pageSize) ? (int)$pageSize : 20;

        return new self(
            search: isset($filter['search']) && is_string($filter['search']) && $filter['search'] !== ''
                ? $filter['search']
                : null,
            type: isset($filter['type']) && is_string($filter['type']) && $filter['type'] !== ''
                ? $filter['type']
                : null,
            isActive: isset($filter['is_active']) && $filter['is_active'] !== ''
                ? filter_var($filter['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : null,
            perPage: max(1, min(100, $perPage)),
        );
    }
}
