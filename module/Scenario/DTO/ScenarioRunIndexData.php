<?php

declare(strict_types=1);

namespace Module\Scenario\DTO;

use App\Support\Pagination;
use Illuminate\Http\Request;
use Module\Scenario\Enums\ScenarioRunStatus;

final readonly class ScenarioRunIndexData
{
    public function __construct(
        public ?string $scenarioId = null,
        public ?ScenarioRunStatus $status = null,
        public bool $finished = false,
        /** @var list<int> */
        public array $createdBy = [],
        public ?string $search = null,
        public ?string $createdFrom = null,
        public ?string $createdTo = null,
        public int $page = 1,
        public int $perPage = Pagination::DEFAULT_SIZE,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $filter = is_array($request->input('filter')) ? $request->array('filter') : [];
        $pagination = Pagination::fromRequest($request);

        $scenarioId = isset($filter['scenario_id']) && is_string($filter['scenario_id']) && $filter['scenario_id'] !== ''
            ? $filter['scenario_id']
            : null;

        $status = isset($filter['status']) && is_string($filter['status']) && $filter['status'] !== ''
            ? ScenarioRunStatus::tryFrom($filter['status'])
            : null;

        $rawCreatedBy = $filter['created_by'] ?? null;
        $createdBy = array_values(array_map(
            intval(...),
            array_filter(is_array($rawCreatedBy) ? $rawCreatedBy : [$rawCreatedBy], is_numeric(...)),
        ));

        $search = is_string($filter['search'] ?? null) ? trim($filter['search']) : '';

        $createdFrom = isset($filter['created_from']) && is_string($filter['created_from']) && $filter['created_from'] !== ''
            ? $filter['created_from']
            : null;

        $createdTo = isset($filter['created_to']) && is_string($filter['created_to']) && $filter['created_to'] !== ''
            ? $filter['created_to']
            : null;

        return new self(
            scenarioId: $scenarioId,
            status: $status,
            finished: !empty($filter['finished']),
            createdBy: $createdBy,
            search: $search !== '' ? $search : null,
            createdFrom: $createdFrom,
            createdTo: $createdTo,
            page: $pagination->number,
            perPage: $pagination->size,
        );
    }
}
