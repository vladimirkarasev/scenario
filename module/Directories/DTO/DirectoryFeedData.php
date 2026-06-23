<?php

declare(strict_types=1);

namespace Module\Directories\DTO;

use Module\Directories\Http\Requests\DirectoryFeedRequest;

final readonly class DirectoryFeedData
{
    public function __construct(
        public bool $parentSet,
        /** Когда parentSet=true: null = uncategorized, uuid = конкретная категория */
        public ?string $parentId,
        public ?string $search,
        public int $page,
        public int $perPage,
    ) {}

    public static function fromRequest(DirectoryFeedRequest $request): self
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

        return new self(
            parentSet: $parentSet,
            parentId: $parentId,
            search: $search,
            page: max(1, $request->integer('page.number', 1)),
            perPage: max(1, min(100, $request->integer('page.size', 20))),
        );
    }
}
