<?php

declare(strict_types=1);

namespace Module\Groups\DTO;

use App\Support\Pagination;
use Illuminate\Http\Request;

final readonly class UserGroupIndexData
{
    public function __construct(
        public ?string $search,
        public ?bool $isActive,
        public Pagination $pagination,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $filter = $request->array('filter');

        $search = isset($filter['search']) && is_string($filter['search']) && $filter['search'] !== ''
            ? $filter['search']
            : null;

        $isActive = isset($filter['is_active']) && $filter['is_active'] !== ''
            ? filter_var($filter['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : null;

        return new self(
            search: $search,
            isActive: $isActive,
            pagination: Pagination::fromRequest($request),
        );
    }
}
