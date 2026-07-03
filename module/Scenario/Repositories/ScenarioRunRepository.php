<?php

declare(strict_types=1);

namespace Module\Scenario\Repositories;

use App\Exceptions\NotFoundException;
use Module\Scenario\Enums\ScenarioErrorCode;
use Module\Scenario\Models\ScenarioRun;

final class ScenarioRunRepository
{
    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): ScenarioRun
    {
        return ScenarioRun::query()->create($attributes);
    }

    public function getById(string $id): ScenarioRun
    {
        return ScenarioRun::query()->find($id)
            ?? throw NotFoundException::from(ScenarioErrorCode::ScenarioRunNotFound);
    }

    public function getByIdInProject(string $id, string $projectId): ScenarioRun
    {
        return ScenarioRun::query()
            ->forProject($projectId)
            ->whereKey($id)
            ->first()
            ?? throw NotFoundException::from(ScenarioErrorCode::ScenarioRunNotFound);
    }

    public function hydrate(ScenarioRun $run): ScenarioRun
    {
        return ScenarioRun::query()
            ->withPlayerRelations()
            ->whereKey($run->getKey())
            ->first()
            ?? throw NotFoundException::from(ScenarioErrorCode::ScenarioRunNotFound);
    }

    public function markUpdatedBy(ScenarioRun $run, int $userId): void
    {
        ScenarioRun::query()->whereKey($run->getKey())->update(['updated_by' => $userId]);
    }

    public function assignActor(ScenarioRun $run, int $userId): void
    {
        ScenarioRun::query()->whereKey($run->getKey())->update([
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }
}
