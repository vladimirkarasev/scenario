<?php

declare(strict_types=1);

namespace Module\Scenario\DTO;

use Module\Scenario\Http\Requests\ScenarioFeedRequest;

final readonly class ScenarioFeedData
{
    public function __construct(
        public bool $parentSet,
        public ?string $parentId,
        public ?string $search,
        public ?string $status,
        public ?string $excludeScenarioId,
        public int $page,
        public int $perPage,
        public ?string $rootId = null,
    ) {
    }

    public static function fromRequest(ScenarioFeedRequest $request): self
    {
        $filter = is_array($request->input('filter')) ? $request->array('filter') : [];

        $parentRaw = $filter['parent_id'] ?? null;
        $parentId = is_string($parentRaw) && $parentRaw !== '' && $parentRaw !== 'null'
            ? $parentRaw
            : null;

        $search = isset($filter['search']) && is_string($filter['search']) && trim($filter['search']) !== ''
            ? trim($filter['search'])
            : null;
        $parentSet = array_key_exists('parent_id', $filter) || $search === null;

        $status = isset($filter['status']) && is_string($filter['status']) && $filter['status'] !== ''
            ? $filter['status']
            : null;

        $excludeScenarioId = isset($filter['exclude_scenario_id']) && is_string(
            $filter['exclude_scenario_id']
        ) && $filter['exclude_scenario_id'] !== ''
            ? $filter['exclude_scenario_id']
            : null;

        $rootId = isset($filter['root_id']) && is_string($filter['root_id']) && $filter['root_id'] !== ''
            ? $filter['root_id']
            : null;

        return new self(
            parentSet: $parentSet,
            parentId: $parentId,
            search: $search,
            status: $status,
            excludeScenarioId: $excludeScenarioId,
            page: max(1, $request->integer('page.number', 1)),
            perPage: max(1, min(100, $request->integer('page.size', 20))),
            rootId: $rootId,
        );
    }
}
