<?php

declare(strict_types=1);

namespace Module\Scenario\Events;

use Module\Scenario\Models\ScenarioRun;

final readonly class ScenarioConditionEvaluated
{
    /** @param  array<string, mixed>  $node */
    public function __construct(
        public ScenarioRun $run,
        public array $node,
        public string $mode,
        public ?string $selectedLabel,
        public ?string $targetNodeId,
    ) {}
}
