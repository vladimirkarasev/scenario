<?php

declare(strict_types=1);

namespace Module\Proxy\DTO;

use App\Support\Pagination;
use Illuminate\Http\Request;

final readonly class ProxyEndpointIndexData
{
    /**
     * @param list<string> $categoryIds
     * @param list<string> $sort
     */
    public function __construct(
        public ?string $search,
        public ?string $type,
        public array $categoryIds,
        public array $sort,
        public Pagination $pagination,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $filter = $request->array('filter');
        $search = isset($filter['search']) && is_string($filter['search']) && trim($filter['search']) !== ''
            ? trim($filter['search'])
            : null;
        $type = isset($filter['type']) && is_string($filter['type']) && $filter['type'] !== ''
            ? $filter['type']
            : null;
        $categoryIds = array_values(array_filter(
            (array) ($filter['category_ids'] ?? []),
            static fn (mixed $id): bool => is_string($id) && $id !== '',
        ));
        $sort = array_values(array_filter(
            explode(',', $request->string('sort', '-created_at')->toString()),
            static fn (string $value): bool => $value !== '',
        ));

        return new self(
            search: $search,
            type: $type,
            categoryIds: $categoryIds,
            sort: $sort,
            pagination: Pagination::fromRequest($request),
        );
    }
}
