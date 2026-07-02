<?php

declare(strict_types=1);

namespace Module\Users\DTO;

use App\Support\Pagination;
use Illuminate\Http\Request;

final readonly class RoleIndexData
{
    public function __construct(
        public ?string $search,
        public Pagination $pagination,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $filter = $request->array('filter');

        $search = isset($filter['search']) && is_string($filter['search']) && $filter['search'] !== ''
            ? $filter['search']
            : null;

        return new self(
            search: $search,
            pagination: Pagination::fromRequest($request),
        );
    }
}
