<?php

declare(strict_types=1);

namespace Module\Proxy\DTO;

use App\Support\Pagination;
use Illuminate\Http\Request;

final readonly class ProxyRequestIndexData
{
    public function __construct(
        public ?int $endpointId,
        public ?string $status,
        public ?string $search,
        /** @var list<string> */
        public array $sort,
        public Pagination $pagination,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $filter = $request->array('filter');

        $endpointId = isset($filter['endpoint_id']) && is_numeric($filter['endpoint_id'])
            ? (int) $filter['endpoint_id']
            : null;

        $status = isset($filter['status']) && is_string($filter['status']) && $filter['status'] !== ''
            ? $filter['status']
            : null;

        $search = isset($filter['search']) && is_string($filter['search']) && trim($filter['search']) !== ''
            ? trim($filter['search'])
            : null;

        $sort = array_values(array_filter(
            explode(',', $request->string('sort', '-received_at')->toString()),
            static fn (string $value): bool => $value !== '',
        ));

        return new self(
            endpointId: $endpointId,
            status: $status,
            search: $search,
            sort: $sort,
            pagination: Pagination::fromRequest($request),
        );
    }
}
