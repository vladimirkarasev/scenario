<?php

declare(strict_types=1);

namespace Module\Scenario\Repositories;

use Module\Scenario\Models\ScenarioRun;

final class ScenarioRunRepository
{
    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): ScenarioRun
    {
        return ScenarioRun::query()->create($attributes);
    }

    public function hydrate(ScenarioRun $run): ScenarioRun
    {
        return ScenarioRun::query()
            ->with(['scenario', 'version', 'revision', 'steps', 'operator.project'])
            ->whereKey($run->getKey())
            ->firstOrFail();
    }
}
