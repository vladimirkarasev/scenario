<?php

declare(strict_types=1);

namespace Module\Scenario\Jobs;

use denis660\Centrifugo\Centrifugo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Module\Scenario\Models\ScenarioRun;
use Module\Scenario\Services\Nodes\Action\ActionStatus;
use Module\Scenario\Services\Nodes\NodeContextKeys;
use Module\Scenario\Services\ScenarioPlayerService;

/**
 * Завершает асинхронный pipeline action-ноды (wait_for_result): переносит output'ы экшенов
 * в контекст прогона, помечает состояние ноды и — при успехе — авто-продвигает прогон к
 * следующему узлу. Итоговое состояние публикуется в канал scenario-run:{id} как run_updated.
 *
 * Диспатчится из Module\Actions\Jobs\ChainStepJob по завершении/сбою цепочки.
 */
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

    public function handle(ScenarioPlayerService $player, Centrifugo $centrifugo): void
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

        $runs = is_array($context[NodeContextKeys::ACTION_RUNS] ?? null) ? $context[NodeContextKeys::ACTION_RUNS] : [];
        $runs[$this->nodeId] = ($this->success ? ActionStatus::Done : ActionStatus::Failed)->value;
        $context[NodeContextKeys::ACTION_RUNS] = $runs;

        $run->forceFill(['context' => $context])->save();

        $run = $this->success
            ? $player->resumeFromActionNode($run, $this->nodeId)
            : $player->getRun($run);

        $payload = $player->payload($run);
        $runPayload = is_array($payload['run'] ?? null) ? $payload['run'] : [];
        $publishedRunId = is_string($runPayload['id'] ?? null) ? $runPayload['id'] : null;

        if ($publishedRunId !== null) {
            $centrifugo->publish("scenario-run:{$publishedRunId}", ['type' => 'run_updated', ...$payload]);
        }
    }
}
