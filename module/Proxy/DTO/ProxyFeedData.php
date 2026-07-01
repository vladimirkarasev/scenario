<?php

declare(strict_types=1);

namespace Module\Proxy\DTO;

use Module\Proxy\Http\Requests\ProxyFeedRequest;

final readonly class ProxyFeedData
{
    public function __construct(
        public bool $parentSet,
        /** Когда parentSet=true: null = без раздела, uuid = конкретный раздел */
        public ?string $parentId,
        public ?string $search,
        public ?string $type,
        public int $page,
        public int $perPage,
    ) {}

    public static function fromRequest(ProxyFeedRequest $request): self
    {
        $filter = is_array($request->input('filter')) ? $request->array('filter') : [];
        $parentSet = array_key_exists('parent_id', $filter);
        $parentRaw = $filter['parent_id'] ?? null;
        $parentId = is_string($parentRaw) && $parentRaw !== '' && $parentRaw !== 'null'
            ? $parentRaw
            : null;

        $search = isset($filter['search']) && is_string($filter['search']) && trim($filter['search']) !== ''
            ? trim($filter['search'])
            : null;

        $type = isset($filter['type']) && is_string($filter['type']) && trim($filter['type']) !== ''
            ? trim($filter['type'])
            : null;

        return new self(
            parentSet: $parentSet,
            parentId: $parentId,
            search: $search,
            type: $type,
            page: max(1, $request->integer('page.number', 1)),
            perPage: max(1, min(100, $request->integer('page.size', 20))),
        );
    }
}
