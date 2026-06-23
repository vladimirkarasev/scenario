<?php

declare(strict_types=1);

namespace Module\Proxy\DTO;

use Illuminate\Http\Request;

final readonly class ProxyRequestIndexData
{
    public function __construct(
        public ?int $endpointId,
        public ?string $status,
        public ?string $search,
        public int $perPage,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $filter = $request->array('filter');

        $endpointId = isset($filter['endpoint_id']) && is_numeric($filter['endpoint_id'])
            ? (int)$filter['endpoint_id']
            : null;

        $status = isset($filter['status']) && is_string($filter['status']) && $filter['status'] !== ''
            ? $filter['status']
            : null;

        $search = isset($filter['search']) && is_string($filter['search']) && trim($filter['search']) !== ''
            ? trim($filter['search'])
            : null;

        $perPage = max(1, min(100, (int)$request->integer('page.size', 20)));

        return new self(
            endpointId: $endpointId,
            status: $status,
            search: $search,
            perPage: $perPage,
        );
    }
}
