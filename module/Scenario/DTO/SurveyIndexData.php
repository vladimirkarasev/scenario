<?php

declare(strict_types=1);

namespace Module\Scenario\DTO;

use App\Support\Pagination;
use Illuminate\Http\Request;

final readonly class SurveyIndexData
{
    public function __construct(
        public ?string $status = null,
        public ?string $scenarioId = null,
        public int $perPage = Pagination::DEFAULT_SIZE,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $filter = is_array($request->input('filter')) ? $request->array('filter') : [];

        $status = isset($filter['status']) && is_string($filter['status']) && $filter['status'] !== ''
            ? $filter['status']
            : null;

        $scenarioId = isset($filter['scenario_id']) && is_string($filter['scenario_id']) && $filter['scenario_id'] !== ''
            ? $filter['scenario_id']
            : null;

        return new self(
            status: $status,
            scenarioId: $scenarioId,
            perPage: Pagination::fromRequest($request)->size,
        );
    }
}
