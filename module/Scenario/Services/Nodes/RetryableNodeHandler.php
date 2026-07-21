<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes;

use Module\Scenario\Models\ScenarioRun;

interface RetryableNodeHandler
{
    /**
     * @param  array<string, mixed>  $node
     */
    public function retry(ScenarioRun $run, array $node): bool;
}
