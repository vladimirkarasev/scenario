<?php

declare(strict_types=1);

namespace Module\Scenario\Events;

use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Models\ScenarioRunStep;

final readonly class ScenarioNodeExited
{
    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $output
     */
    public function __construct(
        public ScenarioRun $run,
        public array $node,
        public ScenarioRunStep $step,
        public array $input,
        public array $output,
    ) {}
}
