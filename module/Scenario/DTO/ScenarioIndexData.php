<?php

declare(strict_types=1);

namespace Module\Scenario\DTO;

use Illuminate\Http\Request;
use Module\Scenario\Enums\ScenarioStatus;

final readonly class ScenarioIndexData
{
    public function __construct(
        public bool $activeOnly = true,
        public ?string $search = null,
        public ?bool $isActive = null,
        public ?ScenarioStatus $status = null,
        /** @var list<string>|null */
        public ?array $tags = null,
        public ?string $categoryId = null,
        /** @var string[]|null */
        public ?array $categoryIds = null,
        public int $perPage = 15,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $filter = $request->array('filter');

        $rawActiveOnly = $filter['active_only'] ?? null;
        $activeOnly = $rawActiveOnly !== null && $rawActiveOnly !== ''
            ? (bool) filter_var($rawActiveOnly, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : true;

        $search = trim(is_string($filter['search'] ?? null) ? $filter['search'] : '');

        $rawIsActive = $filter['is_active'] ?? null;
        $isActive = $rawIsActive !== null && $rawIsActive !== ''
            ? filter_var($rawIsActive, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : null;

        $rawTags = $filter['tags'] ?? null;
        $tags = is_array($rawTags)
            ? array_values(array_filter(
                array_map(static fn (mixed $v): string => is_string($v) ? $v : '', $rawTags),
                static fn (string $v): bool => $v !== '',
            ))
            : null;
        $tags = ! empty($tags) ? $tags : null;

        $categoryId = isset($filter['category_id']) && is_string(
            $filter['category_id'],
        ) && $filter['category_id'] !== ''
            ? $filter['category_id']
            : null;

        $rawCategoryIds = $filter['category_ids'] ?? null;
        $categoryIds = is_array($rawCategoryIds)
            ? array_values(array_filter(
                array_map(static fn (mixed $v): string => is_string($v) ? $v : '', $rawCategoryIds),
                static fn (string $v): bool => $v !== '',
            ))
            : null;
        $categoryIds = ! empty($categoryIds) ? $categoryIds : null;

        $rawStatus = isset($filter['status']) && is_string($filter['status']) && $filter['status'] !== ''
            ? $filter['status']
            : null;
        $status = $rawStatus !== null ? ScenarioStatus::tryFrom($rawStatus) : null;

        $pageSizeRaw = $request->input('page.size');
        $perPage = max(1, min(100, is_numeric($pageSizeRaw) ? (int) $pageSizeRaw : 15));

        return new self(
            activeOnly: $activeOnly,
            search: $search !== '' ? $search : null,
            isActive: $isActive,
            status: $status,
            tags: $tags,
            categoryId: $categoryId,
            categoryIds: $categoryIds,
            perPage: $perPage,
        );
    }
}
