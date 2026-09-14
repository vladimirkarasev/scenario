<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Definition;

use Illuminate\Pagination\LengthAwarePaginator;
use Module\Projects\CurrentProject;
use Module\Scenario\DTO\ScenarioIndexData;
use Module\Scenario\Models\Scenario;

final readonly class ScenariosService
{
    public function __construct(
        private CurrentProject $currentProject,
    ) {
    }

    /** @return LengthAwarePaginator<int, Scenario> */
    public function paginate(ScenarioIndexData $filters): LengthAwarePaginator
    {
        $query = Scenario::query()
            ->with(['categories', 'versions', 'createdBy', 'updatedBy']);

        if ($this->currentProject->id() !== null) {
            $query->forProject($this->currentProject->id());
        }

        $query->activeOnly($filters->activeOnly);
        $query->search($filters->search);
        $query->isActive($filters->isActive);
        $query->status($filters->status);
        $query->tags($filters->tags);
        $query->categoryId($filters->categoryId);
        $query->categoryIds($filters->categoryIds);

        return $query->paginate($filters->perPage, ['*'], 'page[number]');
    }
}
