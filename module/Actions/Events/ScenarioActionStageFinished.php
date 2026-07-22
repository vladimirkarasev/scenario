<?php

declare(strict_types=1);

namespace Module\Actions\Events;

use Module\Actions\Enums\ActionRunStatus;

final readonly class ScenarioActionStageFinished
{
    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>|null  $output
     */
    public function __construct(
        public string $scenarioRunId,
        public string $actionNodeId,
        public string $actionId,
        public string $actionName,
        public string $code,
        public ActionRunStatus $status,
        public array $input,
        public ?array $output,
        public ?string $error,
    ) {}
}
