<?php

declare(strict_types=1);

namespace Module\Actions\Jobs;

use denis660\Centrifugo\Centrifugo;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Module\Actions\Enums\ActionRunStatus;
use Module\Actions\Exceptions\ActionExecutionException;
use Module\Actions\Models\Action;
use Module\Actions\Services\ActionExecutor;

final class ExecuteActionJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    /**
     * @param  array<string, mixed>  $input
     * @param  array<int, int>  $backoff
     */
    public function __construct(
        private readonly string $actionId,
        private readonly array $input,
        int $tries = 1,
        private readonly array $backoff = [60],
    ) {
        $this->tries = max(1, $tries);
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return $this->backoff;
    }

    public function handle(ActionExecutor $executor, Centrifugo $centrifugo): void
    {
        $action = Action::query()->findOrFail($this->actionId);
        $result = $executor->execute($action, $this->input, $this->attempts());

        $scenarioRunId = $this->stringOrNull($this->input['scenario_run_id'] ?? null);

        if ($result->status === ActionRunStatus::Failed) {
            if ($this->attempts() >= $this->tries && $scenarioRunId !== null) {
                $centrifugo->publish("scenario-run:{$scenarioRunId}", [
                    'type' => 'action_failed',
                    'action_id' => $this->actionId,
                    'error' => $result->error,
                ]);
            }

            throw new ActionExecutionException($result->error ?? 'Action failed');
        }

        if ($scenarioRunId !== null) {
            $centrifugo->publish("scenario-run:{$scenarioRunId}", [
                'type' => 'action_completed',
                'action_id' => $this->actionId,
                'status' => $result->status->value,
                'output' => $result->output,
            ]);
        }
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_scalar($value) && (string)$value !== '' ? (string)$value : null;
    }
}
