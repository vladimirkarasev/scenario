<?php

declare(strict_types=1);

namespace Module\Actions\Jobs;

use denis660\Centrifugo\Centrifugo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Module\Actions\Enums\ActionRunStatus;
use Module\Actions\Exceptions\ActionExecutionException;
use Module\Actions\Models\Action;
use Module\Actions\Services\ActionExecutor;
use Throwable;

final class ChainStepJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    /**
     * @param array<int, string>    $remainingActionIds
     * @param array<int, string>    $failedActionIds
     * @param array<string, mixed>  $context
     * @param array<int, int>       $backoff
     * @param array<string, string> $codeMap            actionId => code override (если нет — используется $action->code)
     */
    public function __construct(
        private readonly string $actionId,
        private readonly array $remainingActionIds,
        private readonly array $failedActionIds,
        private readonly array $context,
        int $tries = 1,
        private readonly array $backoff = [60],
        private readonly ?string $scenarioRunId = null,
        private readonly array $codeMap = [],
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

        $result = $executor->execute($action, $this->context, $this->attempts());

        $code = $this->codeMap[$this->actionId] ?? $action->code;

        if ($result->status === ActionRunStatus::Failed) {
            if ($this->attempts() >= $this->tries && $this->scenarioRunId !== null) {
                $centrifugo->publish("scenario-run:{$this->scenarioRunId}", [
                    'type' => 'action_failed',
                    'action_id' => $this->actionId,
                    'code' => $code,
                    'error' => $result->error,
                ]);
            }

            throw new ActionExecutionException($result->error ?? 'Action failed');
        }

        $context = $this->context;
        $existing = is_array($context[$code] ?? null) ? $context[$code] : [];
        $context[$code] = [...$existing, ...($result->output ?? [])];

        if ($this->scenarioRunId !== null) {
            $centrifugo->publish("scenario-run:{$this->scenarioRunId}", [
                'type' => 'action_completed',
                'action_id' => $this->actionId,
                'code' => $code,
                'status' => $result->status->value,
                'output' => $result->output,
            ]);
        }

        if ($this->remainingActionIds === []) {
            if ($this->scenarioRunId !== null) {
                $centrifugo->publish("scenario-run:{$this->scenarioRunId}", [
                    'type' => 'chain_completed',
                    'context' => $context,
                ]);
            }

            return;
        }

        [$nextActionId, $remaining] = $this->shift($this->remainingActionIds);

        self::dispatch(
            $nextActionId,
            $remaining,
            $this->failedActionIds,
            $context,
            $this->tries,
            $this->backoff,
            $this->scenarioRunId,
            $this->codeMap,
        );
    }

    public function failed(Throwable $exception): void
    {
        if ($this->failedActionIds === []) {
            return;
        }

        $failedContext = $this->context + [
            'failed_action_id' => $this->actionId,
            'failed_error' => $exception->getMessage(),
        ];

        [$firstFailed, $remainingFailed] = $this->shift($this->failedActionIds);

        self::dispatch(
            $firstFailed,
            $remainingFailed,
            [],
            $failedContext,
            $this->tries,
            $this->backoff,
            $this->scenarioRunId,
            $this->codeMap,
        );
    }

    /**
     * @param  array<int, string>                      $ids
     * @return array{0: string, 1: array<int, string>}
     */
    private function shift(array $ids): array
    {
        $values = array_values($ids);
        $head = (string) array_shift($values);

        return [$head, $values];
    }
}
