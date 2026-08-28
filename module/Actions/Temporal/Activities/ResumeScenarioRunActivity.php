<?php

declare(strict_types=1);

namespace Module\Actions\Temporal\Activities;

use Module\Scenario\Jobs\ResumeScenarioActionNodeJob;
use Module\Scenario\Services\Runtime\ScenarioPlayerService;

final readonly class ResumeScenarioRunActivity implements ResumeScenarioRunActivityInterface
{
    public function __construct(
        private ScenarioPlayerService $player,
    ) {}

    public function resume(string $runId, string $nodeId, bool $success, array $actionContext): void
    {
        (new ResumeScenarioActionNodeJob($runId, $nodeId, $success, $actionContext))->handle($this->player);
    }
}
