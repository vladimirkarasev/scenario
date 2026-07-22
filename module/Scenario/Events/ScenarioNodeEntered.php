<?php

declare(strict_types=1);

namespace Module\Scenario\Events;

use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioRunStep;

final readonly class ScenarioNodeEntered
{
    /** @param  array<string, mixed>  $node */
    public function __construct(
        public ScenarioRun $run,
        public array $node,
        public ScenarioRunStep $step,
    ) {}
}
