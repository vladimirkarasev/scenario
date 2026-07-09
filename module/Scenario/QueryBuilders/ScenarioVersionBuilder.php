<?php

declare(strict_types=1);

namespace Module\Scenario\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Module\Scenario\Models\ScenarioVersion;

/**
 * @extends Builder<ScenarioVersion>
 */
final class ScenarioVersionBuilder extends Builder
{
    public function forScenario(string $scenarioId): self
    {
        return $this->where('scenario_id', $scenarioId);
    }

    public function active(): self
    {
        return $this->where('status', 'active');
    }
}
