<?php

declare(strict_types=1);

namespace Module\Scenario\Repositories;

use Module\Scenario\Models\ScenarioVersion;
use Module\Scenario\Models\ScenarioVersionRevision;

final readonly class ScenarioVersionRevisionRepository
{
    public function getLastRevision(ScenarioVersion $version): ?ScenarioVersionRevision
    {
        return $version->revisions()->first();
    }
}
