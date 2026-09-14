<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Runtime;

use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioVersion;
use RuntimeException;

final readonly class ScenarioRunVersionResolver
{
    public function resolve(ScenarioRun $run): ScenarioVersion
    {
        return $run->version ?? throw new RuntimeException('Run version is not loaded.');
    }
}
