<?php

declare(strict_types=1);

namespace Module\Scenario\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Event;
use Module\Scenario\Enums\ScenarioContextKey;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Services\Nodes\Action\ActionStatus;
use Module\Scenario\Services\Runtime\ScenarioPlayerService;
use App\Events\CentrifugoMessagePublished;

final class ResumeScenarioActionNodeJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @param  array<string, mixed>  $actionContext  накопленные output'ы экшенов по code */
    public function __construct(
        private readonly string $runId,
        private readonly string $nodeId,
        private readonly bool $success,
        private readonly array $actionContext = [],
    ) {
    }

    public function handle(ScenarioPlayerService $player): void
    {
        $run = ScenarioRun::query()->find($this->runId);

        if ($run === null) {
            return;
        }

        $context = is_array($run->context) ? $run->context : [];

        foreach ($this->actionContext as $key => $value) {
            if ($key === '' || str_starts_with($key, '_')) {
                continue;
            }

            if (in_array($key, ['scenario_run_id', 'scenario_node_id', 'failed_action_id', 'failed_error'], true)) {
                continue;
            }

            $context[$key] = $value;
        }

        $runs = is_array($context[ScenarioContextKey::ActionRuns->value] ?? null)
            ? $context[ScenarioContextKey::ActionRuns->value]
            : [];
        $runs[$this->nodeId] = ($this->success ? ActionStatus::Done : ActionStatus::Failed)->value;
        $context[ScenarioContextKey::ActionRuns->value] = $runs;

        $run->forceFill(['context' => $context])->save();

        $run = $this->success
            ? $player->resumeFromActionNode($run, $this->nodeId)
            : $player->getRun($run);

        $payload = $player->payload($run);
        $runPayload = is_array($payload['run'] ?? null) ? $payload['run'] : [];
        $publishedRunId = is_string($runPayload['id'] ?? null) ? $runPayload['id'] : null;

        if ($publishedRunId !== null) {
            Event::dispatch(new CentrifugoMessagePublished("scenario-run:{$publishedRunId}", ['type' => 'run_updated', ...$payload]));
        }
    }
}
