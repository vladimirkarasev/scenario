<?php

declare(strict_types=1);

namespace Module\Proxy\DTO;

use App\Support\Pagination;
use Illuminate\Http\Request;

final readonly class ProxyConnectionIndexData
{
    /** @param list<string> $sort */
    public function __construct(
        public ?string $search,
        public ?string $credentialType,
        public array $sort,
        public Pagination $pagination,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $filter = $request->array('filter');
        $search = isset($filter['search']) && is_string($filter['search']) && trim($filter['search']) !== ''
            ? trim($filter['search'])
            : null;
        $credentialType = isset($filter['credential_type'])
            && is_string($filter['credential_type'])
            && $filter['credential_type'] !== ''
                ? $filter['credential_type']
                : null;
        $sort = array_values(array_filter(
            explode(',', $request->string('sort', '-created_at')->toString()),
            static fn (string $value): bool => $value !== '',
        ));

        return new self(
            search: $search,
            credentialType: $credentialType,
            sort: $sort,
            pagination: Pagination::fromRequest($request),
        );
    }
}
